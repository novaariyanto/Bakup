<?php

namespace App\Enums\MyDumper;

enum MyDumperExportType: string
{
    case Full = 'full';
    case SchemaOnly = 'schema_only';
    case DataOnly = 'data_only';
    case SelectedTables = 'selected_tables';
    case ExcludeTables = 'exclude_tables';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Export',
            self::SchemaOnly => 'Schema Only',
            self::DataOnly => 'Data Only',
            self::SelectedTables => 'Selected Tables',
            self::ExcludeTables => 'Exclude Tables',
        };
    }

    public function requiresTableSelection(): bool
    {
        return in_array($this, [self::SelectedTables, self::ExcludeTables], true);
    }
}
