<?php

namespace App\Services\MyDumper;

use App\Enums\ScheduleType;
use App\Exceptions\ScheduleException;
use App\Models\MyDumperExportProfile;
use App\Repositories\MyDumperExportRepository;
use App\Services\BaseService;
use App\Support\MyDumperLogger;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MyDumperScheduleService extends BaseService
{
    public function __construct(
        private readonly MyDumperExportRepository $repository,
        private readonly MyDumperLogger $logger,
    ) {}

    public function isDue(MyDumperExportProfile $profile, ?Carbon $now = null): bool
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

    public function calculateNextRunAt(MyDumperExportProfile $profile, ?Carbon $from = null): ?Carbon
    {
        if ($profile->schedule_type === ScheduleType::Manual || ! $profile->is_active) {
            return null;
        }

        $from ??= now();

        return match ($profile->schedule_type) {
            ScheduleType::Hourly => $from->copy()->addHour()->startOfHour(),
            ScheduleType::Daily => $this->nextDailyRun($profile, $from),
            ScheduleType::Weekly => $this->nextWeeklyRun($profile, $from),
            ScheduleType::Monthly => $this->nextMonthlyRun($profile, $from),
            ScheduleType::CustomCron => $this->nextCronRun($profile, $from),
            ScheduleType::Manual => null,
        };
    }

    public function syncNextRunAt(MyDumperExportProfile $profile): MyDumperExportProfile
    {
        $profile->update([
            'next_run_at' => $this->calculateNextRunAt($profile),
        ]);

        return $profile->fresh();
    }

    /**
     * @return Collection<int, MyDumperExportProfile>
     */
    public function dueProfiles(?Carbon $now = null): Collection
    {
        $now ??= now();

        return MyDumperExportProfile::query()
            ->where('is_active', true)
            ->where('schedule_type', '!=', ScheduleType::Manual->value)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->orderBy('next_run_at')
            ->get()
            ->filter(fn (MyDumperExportProfile $profile) => $this->isDue($profile, $now));
    }

    public function processDueProfiles(?Carbon $now = null): int
    {
        $now ??= now();
        $processed = 0;

        foreach ($this->dueProfiles($now) as $profile) {
            if ($this->repository->hasRunningExport($profile)) {
                $this->logger->warning('Skipping scheduled mydumper export because another run is active', [
                    'profile_id' => $profile->id,
                ]);

                continue;
            }

            try {
                app(MyDumperExecutionService::class)->dispatchFromProfile($profile);
                $this->markScheduledRun($profile, $now);
                $processed++;
            } catch (\Throwable $exception) {
                $this->logger->error('Scheduled mydumper export failed to dispatch', [
                    'profile_id' => $profile->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    public function markScheduledRun(MyDumperExportProfile $profile, ?Carbon $ranAt = null): MyDumperExportProfile
    {
        $ranAt ??= now();

        $profile->update([
            'last_scheduled_run_at' => $ranAt,
            'next_run_at' => $this->calculateNextRunAt($profile, $ranAt),
        ]);

        return $profile->fresh();
    }

    private function nextDailyRun(MyDumperExportProfile $profile, Carbon $from): Carbon
    {
        $time = $profile->schedule_time ?? '02:00';
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        $candidate = $from->copy()->setTime((int) $hour, (int) $minute, 0);

        if ($candidate->lte($from)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function nextWeeklyRun(MyDumperExportProfile $profile, Carbon $from): Carbon
    {
        $dayOfWeek = $profile->schedule_day_of_week ?? 1;
        $time = $profile->schedule_time ?? '02:00';
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        $candidate = $from->copy()->next($dayOfWeek)->setTime((int) $hour, (int) $minute, 0);

        if ($candidate->lte($from)) {
            $candidate->addWeek();
        }

        return $candidate;
    }

    private function nextMonthlyRun(MyDumperExportProfile $profile, Carbon $from): Carbon
    {
        $day = min(28, max(1, (int) ($profile->schedule_day_of_month ?? 1)));
        $time = $profile->schedule_time ?? '02:00';
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        $candidate = $from->copy()->day($day)->setTime((int) $hour, (int) $minute, 0);

        if ($candidate->lte($from)) {
            $candidate->addMonthNoOverflow()->day($day);
        }

        return $candidate;
    }

    private function nextCronRun(MyDumperExportProfile $profile, Carbon $from): Carbon
    {
        $expression = $profile->schedule_cron;

        if ($expression === null || $expression === '') {
            throw ScheduleException::invalidCron('Cron expression kosong.');
        }

        if (! CronExpression::isValidExpression($expression)) {
            throw ScheduleException::invalidCron($expression);
        }

        return Carbon::parse(CronExpression::factory($expression)->getNextRunDate($from->toDateTimeString()));
    }
}
