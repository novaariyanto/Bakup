<?php

namespace App\Enums;

enum BackupStage: string
{
    case Preparing = 'preparing';
    case Connecting = 'connecting';
    case ReadingTables = 'reading_tables';
    case DumpingDatabase = 'dumping_database';
    case Compressing = 'compressing';
    case Uploading = 'uploading';
    case Cleaning = 'cleaning';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Preparing',
            self::Connecting => 'Connecting',
            self::ReadingTables => 'Reading Tables',
            self::DumpingDatabase => 'Dumping Database',
            self::Compressing => 'Compressing',
            self::Uploading => 'Uploading',
            self::Cleaning => 'Cleaning',
            self::Finished => 'Finished',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Preparing => 1,
            self::Connecting => 2,
            self::ReadingTables => 3,
            self::DumpingDatabase => 4,
            self::Compressing => 5,
            self::Uploading => 6,
            self::Cleaning => 7,
            self::Finished => 8,
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Preparing,
            self::Connecting,
            self::ReadingTables,
            self::DumpingDatabase,
            self::Compressing,
            self::Uploading,
            self::Cleaning,
            self::Finished,
        ];
    }

    public static function progressPercent(?string $stage): int
    {
        if ($stage === null || $stage === '') {
            return 0;
        }

        $current = self::tryFrom($stage);

        if ($current === null) {
            return 0;
        }

        if ($current === self::Finished) {
            return 100;
        }

        return (int) round(($current->order() / self::Finished->order()) * 100);
    }
}
