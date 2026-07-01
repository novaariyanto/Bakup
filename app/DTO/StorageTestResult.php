<?php

namespace App\DTO;

readonly class StorageTestResult
{
    public function __construct(
        public bool $success,
        public ?string $status = null,
        public ?string $resolvedPath = null,
        public ?string $host = null,
        public ?string $bucket = null,
        public ?string $region = null,
        public ?string $endpoint = null,
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
        return array_filter([
            'status' => $this->status,
            'resolved_path' => $this->resolvedPath,
            'host' => $this->host,
            'bucket' => $this->bucket,
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'tested_at' => now()->toIso8601String(),
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'resolvedPath' => $this->resolvedPath,
            'host' => $this->host,
            'bucket' => $this->bucket,
            'region' => $this->region,
            'endpoint' => $this->endpoint,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
