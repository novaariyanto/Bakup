<?php

namespace App\Services\Schedule;

use App\Enums\ScheduleType;
use App\Exceptions\BackupExecutionException;
use App\Exceptions\ScheduleException;
use App\Models\BackupProfile;
use App\Repositories\BackupHistoryRepository;
use App\Services\Backup\BackupExecutionService;
use App\Services\BaseService;
use App\Support\BackupLogger;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScheduleService extends BaseService
{
    public function __construct(
        private readonly BackupExecutionService $executionService,
        private readonly BackupHistoryRepository $historyRepository,
        private readonly BackupLogger $logger,
    ) {}

    public function isDue(BackupProfile $profile, ?Carbon $now = null): bool
    {
        $now ??= now();

        if (! $profile->is_active || $profile->schedule_type === ScheduleType::Manual) {
            return false;
        }

        if ($profile->next_run_at === null) {
            return false;
        }

        return $profile->next_run_at->lte($now);
    }

    public function calculateNextRunAt(BackupProfile $profile, ?Carbon $from = null): ?Carbon
    {
        if ($profile->schedule_type === ScheduleType::Manual || ! $profile->is_active) {
            return null;
        }

        $from ??= now();

        return match ($profile->schedule_type) {
            ScheduleType::Hourly => $this->nextHourlyRun($from),
            ScheduleType::Daily => $this->nextDailyRun($profile, $from),
            ScheduleType::Weekly => $this->nextWeeklyRun($profile, $from),
            ScheduleType::Monthly => $this->nextMonthlyRun($profile, $from),
            ScheduleType::CustomCron => $this->nextCronRun($profile, $from),
            ScheduleType::Manual => null,
        };
    }

    public function syncNextRunAt(BackupProfile $profile): BackupProfile
    {
        $profile->update([
            'next_run_at' => $this->calculateNextRunAt($profile),
        ]);

        return $profile->fresh();
    }

    /**
     * @return Collection<int, BackupProfile>
     */
    public function dueProfiles(?Carbon $now = null): Collection
    {
        $now ??= now();

        return BackupProfile::query()
            ->where('is_active', true)
            ->where('schedule_type', '!=', ScheduleType::Manual->value)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->get()
            ->filter(fn (BackupProfile $profile) => $this->isDue($profile, $now));
    }

    public function processDueProfiles(?Carbon $now = null): int
    {
        $now ??= now();
        $processed = 0;

        foreach ($this->dueProfiles($now) as $profile) {
            if ($this->historyRepository->hasRunningBackup($profile)) {
                $this->logger->warning('Skipping scheduled backup because another run is active', [
                    'profile_id' => $profile->id,
                ]);

                continue;
            }

            try {
                $this->executionService->dispatch($profile);
                $this->markScheduledRun($profile, $now);
                $processed++;
            } catch (BackupExecutionException $exception) {
                $this->logger->warning('Scheduled backup dispatch failed', [
                    'profile_id' => $profile->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($processed > 0) {
            $this->logger->info('Scheduled backups processed', [
                'count' => $processed,
            ]);
        }

        return $processed;
    }

    public function markScheduledRun(BackupProfile $profile, ?Carbon $ranAt = null): BackupProfile
    {
        $ranAt ??= now();

        $profile->update([
            'last_scheduled_run_at' => $ranAt,
            'next_run_at' => $this->calculateNextRunAt($profile, $ranAt),
        ]);

        return $profile->fresh();
    }

    private function nextHourlyRun(Carbon $from): Carbon
    {
        return $from->copy()->startOfHour()->addHour();
    }

    private function nextDailyRun(BackupProfile $profile, Carbon $from): Carbon
    {
        $next = $from->copy()->setTimeFromTimeString($this->scheduleTimeString($profile));

        if ($next->lte($from)) {
            $next->addDay();
        }

        return $next;
    }

    private function nextWeeklyRun(BackupProfile $profile, Carbon $from): Carbon
    {
        if ($profile->schedule_day_of_week === null) {
            throw ScheduleException::missingScheduleConfig('schedule_day_of_week');
        }

        $targetDay = (int) $profile->schedule_day_of_week;
        $time = $this->scheduleTimeString($profile);
        $cursor = $from->copy()->startOfDay();

        for ($i = 0; $i < 14; $i++) {
            if ((int) $cursor->dayOfWeek === $targetDay) {
                $candidate = $cursor->copy()->setTimeFromTimeString($time);

                if ($candidate->gt($from)) {
                    return $candidate;
                }
            }

            $cursor->addDay();
        }

        return $from->copy()->addWeek()->setTimeFromTimeString($time);
    }

    private function nextMonthlyRun(BackupProfile $profile, Carbon $from): Carbon
    {
        if ($profile->schedule_day_of_month === null) {
            throw ScheduleException::missingScheduleConfig('schedule_day_of_month');
        }

        $targetDay = (int) $profile->schedule_day_of_month;
        $next = $this->applyDayOfMonth(
            $from->copy()->setTimeFromTimeString($this->scheduleTimeString($profile)),
            $targetDay,
        );

        if ($next->lte($from)) {
            $next = $this->applyDayOfMonth(
                $from->copy()->addMonth()->setTimeFromTimeString($this->scheduleTimeString($profile)),
                $targetDay,
            );
        }

        return $next;
    }

    private function nextCronRun(BackupProfile $profile, Carbon $from): Carbon
    {
        $expression = trim((string) ($profile->schedule_cron ?? ''));

        if ($expression === '') {
            throw ScheduleException::missingScheduleConfig('schedule_cron');
        }

        try {
            $cron = new CronExpression($expression);
        } catch (\InvalidArgumentException) {
            throw ScheduleException::invalidCron($expression);
        }

        return Carbon::instance(
            $cron->getNextRunDate($from->toDateTimeString(), timeZone: $from->timezoneName, allowCurrentDate: false)
        );
    }

    private function scheduleTimeString(BackupProfile $profile): string
    {
        $time = $profile->schedule_time;

        if ($time === null || $time === '') {
            throw ScheduleException::missingScheduleConfig('schedule_time');
        }

        return substr((string) $time, 0, 5);
    }

    private function applyDayOfMonth(Carbon $date, int $day): Carbon
    {
        $day = min($day, $date->daysInMonth);

        return $date->day($day);
    }
}
