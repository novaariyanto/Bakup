<?php

namespace App\Enums;

enum TableDumpMode: string
{
    case WithData = 'with_data';
    case StructureOnly = 'structure_only';
    case Exclude = 'exclude';

    public function label(): string
    {
        return match ($this) {
            self::WithData => 'With Data',
            self::StructureOnly => 'Structure Only',
            self::Exclude => 'Exclude',
        };
    }
}
