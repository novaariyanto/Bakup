<?php

namespace App\DTO;

readonly class DatabaseTableInfo
{
    public function __construct(
        public string $name,
        public ?string $engine = null,
        public ?int $rows = null,
        public ?string $size = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'engine' => $this->engine,
            'rows' => $this->rows,
            'size' => $this->size,
        ];
    }
}
