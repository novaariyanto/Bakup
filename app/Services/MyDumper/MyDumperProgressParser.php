<?php

namespace App\Services\MyDumper;

use App\Services\BaseService;

class MyDumperProgressParser extends BaseService
{
    /**
     * @return array{
     *     current_table: ?string,
     *     current_file: ?string,
     *     rows_exported: ?int,
     *     tables_completed: ?int,
     *     tables_total: ?int,
     *     progress_percent: ?int
     * }
     */
    public function parse(string $line): array
    {
        $result = [
            'current_table' => null,
            'current_file' => null,
            'rows_exported' => null,
            'tables_completed' => null,
            'tables_total' => null,
            'progress_percent' => null,
        ];

        if (preg_match('/(?:Exporting|Dumping)\s+table[:\s]+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $line, $matches) === 1) {
            $result['current_table'] = $matches[1];
        }

        if (preg_match('/(?:Writing|Created)\s+file[:\s]+([^\s]+)/i', $line, $matches) === 1) {
            $result['current_file'] = $matches[1];
        }

        if (preg_match('/(\d+)\s+rows?\s+exported/i', $line, $matches) === 1) {
            $result['rows_exported'] = (int) $matches[1];
        }

        if (preg_match('/table\s+(\d+)\s+of\s+(\d+)/i', $line, $matches) === 1) {
            $result['tables_completed'] = (int) $matches[1];
            $result['tables_total'] = (int) $matches[2];
            $result['progress_percent'] = (int) round(((int) $matches[1] / max(1, (int) $matches[2])) * 100);
        }

        if (preg_match('/(\d+)%/i', $line, $matches) === 1) {
            $result['progress_percent'] = (int) $matches[1];
        }

        return $result;
    }

    public function estimateProgress(int $tablesCompleted, int $tablesTotal, int $basePercent = 10): int
    {
        if ($tablesTotal <= 0) {
            return $basePercent;
        }

        $dumpRange = 60;
        $tableProgress = (int) round(($tablesCompleted / $tablesTotal) * $dumpRange);

        return min(70, $basePercent + $tableProgress);
    }
}
