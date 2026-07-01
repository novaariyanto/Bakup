<?php

namespace App\DTO;

readonly class NotificationTestResult
{
    public function __construct(
        public bool $success,
        public ?string $status = null,
        public ?string $recipient = null,
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
            'recipient' => $this->recipient,
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
            'recipient' => $this->recipient,
            'endpoint' => $this->endpoint,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
