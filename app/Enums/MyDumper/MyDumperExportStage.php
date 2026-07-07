<?php

namespace App\Enums\MyDumper;

enum MyDumperExportStage: string
{
    case Queued = 'queued';
    case Validating = 'validating';
    case Dumping = 'dumping';
    case Uploading = 'uploading';
    case Verifying = 'verifying';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Validating => 'Validating',
            self::Dumping => 'Dumping',
            self::Uploading => 'Uploading',
            self::Verifying => 'Verifying',
            self::Finished => 'Finished',
        };
    }

    public function progressPercent(): int
    {
        return match ($this) {
            self::Queued => 0,
            self::Validating => 5,
            self::Dumping => 40,
            self::Uploading => 70,
            self::Verifying => 90,
            self::Finished => 100,
        };
    }
}
