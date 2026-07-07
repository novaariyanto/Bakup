<?php

namespace App\DTO\MyDumper;

use App\Enums\MyDumper\MyDumperLockMode;

class MyDumperExportOptions
{
    public function __construct(
        public bool $buildEmptyFiles = false,
        public ?int $chunkFilesize = null,
        public ?int $rows = null,
        public ?int $statementSize = null,
        public ?int $longQueryGuard = null,
        public bool $killLongQueries = false,
        public MyDumperLockMode $lockMode = MyDumperLockMode::Auto,
        public bool $trxConsistencyOnly = false,
        public bool $skipDefiner = false,
        public bool $skipTriggers = false,
        public bool $skipEvents = false,
        public bool $skipRoutines = false,
        public bool $skipViews = false,
        public bool $skipConstraints = false,
        public bool $skipIndexes = false,
        public bool $skipGeneratedFields = false,
        public ?string $regexInclude = null,
        public ?string $regexExclude = null,
        public bool $buildMetadata = true,
        public bool $daemonMode = false,
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            buildEmptyFiles: (bool) ($data['build_empty_files'] ?? false),
            chunkFilesize: isset($data['chunk_filesize']) ? (int) $data['chunk_filesize'] : null,
            rows: isset($data['rows']) ? (int) $data['rows'] : null,
            statementSize: isset($data['statement_size']) ? (int) $data['statement_size'] : null,
            longQueryGuard: isset($data['long_query_guard']) ? (int) $data['long_query_guard'] : null,
            killLongQueries: (bool) ($data['kill_long_queries'] ?? false),
            lockMode: isset($data['lock_mode'])
                ? MyDumperLockMode::from($data['lock_mode'])
                : MyDumperLockMode::Auto,
            trxConsistencyOnly: (bool) ($data['trx_consistency_only'] ?? false),
            skipDefiner: (bool) ($data['skip_definer'] ?? false),
            skipTriggers: (bool) ($data['skip_triggers'] ?? false),
            skipEvents: (bool) ($data['skip_events'] ?? false),
            skipRoutines: (bool) ($data['skip_routines'] ?? false),
            skipViews: (bool) ($data['skip_views'] ?? false),
            skipConstraints: (bool) ($data['skip_constraints'] ?? false),
            skipIndexes: (bool) ($data['skip_indexes'] ?? false),
            skipGeneratedFields: (bool) ($data['skip_generated_fields'] ?? false),
            regexInclude: $data['regex_include'] ?? null,
            regexExclude: $data['regex_exclude'] ?? null,
            buildMetadata: (bool) ($data['build_metadata'] ?? true),
            daemonMode: (bool) ($data['daemon_mode'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'build_empty_files' => $this->buildEmptyFiles,
            'chunk_filesize' => $this->chunkFilesize,
            'rows' => $this->rows,
            'statement_size' => $this->statementSize,
            'long_query_guard' => $this->longQueryGuard,
            'kill_long_queries' => $this->killLongQueries,
            'lock_mode' => $this->lockMode->value,
            'trx_consistency_only' => $this->trxConsistencyOnly,
            'skip_definer' => $this->skipDefiner,
            'skip_triggers' => $this->skipTriggers,
            'skip_events' => $this->skipEvents,
            'skip_routines' => $this->skipRoutines,
            'skip_views' => $this->skipViews,
            'skip_constraints' => $this->skipConstraints,
            'skip_indexes' => $this->skipIndexes,
            'skip_generated_fields' => $this->skipGeneratedFields,
            'regex_include' => $this->regexInclude,
            'regex_exclude' => $this->regexExclude,
            'build_metadata' => $this->buildMetadata,
            'daemon_mode' => $this->daemonMode,
        ];
    }
}
