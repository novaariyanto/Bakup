<?php

namespace App\Support\Backup\Dumpers;

use Spatie\DbDumper\Databases\MySql;

class StructureAwareMySql extends MySql
{
    protected bool $includeViews = false;

    /** @var list<string> */
    protected array $structureOnlyTables = [];

    /** @var list<string> */
    protected array $savedExcludeTablesForStructureOnly = [];

    public function setIncludeViews(bool $includeViews = true): self
    {
        $this->includeViews = $includeViews;

        return $this;
    }

    /**
     * @param  list<string>  $structureOnlyTables
     */
    public function setStructureOnlyTables(array $structureOnlyTables): self
    {
        $this->structureOnlyTables = array_values(array_unique(array_filter($structureOnlyTables)));

        return $this;
    }

    public function dumpToFile(string $dumpFile): void
    {
        $this->beginStructureOnlyMainDump();

        try {
            parent::dumpToFile($dumpFile);
            $this->appendStructureOnlyTables($dumpFile);
        } finally {
            $this->endStructureOnlyMainDump();
        }
    }

    protected function beginStructureOnlyMainDump(): void
    {
        if ($this->structureOnlyTables === []) {
            return;
        }

        $this->savedExcludeTablesForStructureOnly = $this->excludeTables;
        $this->excludeTables = array_values(array_unique(array_merge(
            $this->excludeTables,
            $this->structureOnlyTables,
        )));
    }

    protected function endStructureOnlyMainDump(): void
    {
        if ($this->structureOnlyTables === []) {
            return;
        }

        $this->excludeTables = $this->savedExcludeTablesForStructureOnly;
    }

    protected function appendStructureOnlyTables(string $dumpFile): void
    {
        if ($this->structureOnlyTables === []) {
            return;
        }

        $tempFile = $dumpFile.'.structure-only.sql';
        $tables = $this->structureOnlyTables;

        $structureDumper = clone $this;
        $structureDumper->structureOnlyTables = [];
        $structureDumper->excludeTables = [];
        $structureDumper->includeTables($tables);
        $structureDumper->doNotDumpData();
        $structureDumper->dumpToFile($tempFile);

        $contents = file_get_contents($tempFile);
        @unlink($tempFile);

        if (is_string($contents) && $contents !== '') {
            file_put_contents($dumpFile, PHP_EOL.$contents, FILE_APPEND);
        }
    }
}
