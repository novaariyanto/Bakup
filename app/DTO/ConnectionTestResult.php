<?php

namespace App\DTO;

readonly class ConnectionTestResult
{
    public function __construct(
        public bool $success,
        public ?string $mysqlVersion = null,
        public ?string $databaseSize = null,
        public ?int $databaseSizeBytes = null,
        public ?int $totalTables = null,
        public ?string $characterSet = null,
        public ?string $collation = null,
        public ?string $storageEngine = null,
        public ?string $status = null,
        public ?string $errorMessage = null,
    ) {}

    public static function failed(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
        );
    }

    public function toMetadata(): array
    {
        return [
            'mysql_version' => $this->mysqlVersion,
            'database_size' => $this->databaseSize,
            'database_size_bytes' => $this->databaseSizeBytes,
            'total_tables' => $this->totalTables,
            'character_set' => $this->characterSet,
            'collation' => $this->collation,
            'storage_engine' => $this->storageEngine,
            'status' => $this->status,
            'tested_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'mysqlVersion' => $this->mysqlVersion,
            'databaseSize' => $this->databaseSize,
            'databaseSizeBytes' => $this->databaseSizeBytes,
            'totalTables' => $this->totalTables,
            'characterSet' => $this->characterSet,
            'collation' => $this->collation,
            'storageEngine' => $this->storageEngine,
            'status' => $this->status,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
