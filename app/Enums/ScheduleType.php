<?php

namespace App\Enums;

enum ScheduleType: string
{
    case Manual = 'manual';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case CustomCron = 'custom_cron';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Hourly => 'Setiap Jam',
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
            self::CustomCron => 'Custom Cron',
        };
    }

    public function requiresTime(): bool
    {
        return in_array($this, [self::Daily, self::Weekly, self::Monthly], true);
    }

    public function requiresDayOfWeek(): bool
    {
        return $this === self::Weekly;
    }

    public function requiresDayOfMonth(): bool
    {
        return $this === self::Monthly;
    }

    public function requiresCron(): bool
    {
        return $this === self::CustomCron;
    }
}
