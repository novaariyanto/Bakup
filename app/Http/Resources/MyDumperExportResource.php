<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyDumperExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'profile_id' => $this->profile_id,
            'connection_id' => $this->connection_id,
            'storage_destination_id' => $this->storage_destination_id,
            'name' => $this->name,
            'database' => $this->database,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'current_stage' => $this->current_stage?->value,
            'thread' => $this->thread,
            'compression' => $this->compression,
            'output_path' => $this->output_path,
            'command' => $this->command,
            'total_size' => $this->total_size,
            'formatted_size' => $this->formattedSize(),
            'file_count' => $this->file_count,
            'duration' => $this->duration,
            'formatted_duration' => $this->formattedDuration(),
            'exit_code' => $this->exit_code,
            'progress_percent' => $this->progress_percent,
            'current_table' => $this->current_table,
            'checksum' => $this->checksum,
            'verification_status' => $this->verification_status,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'message' => $this->message,
        ];
    }
}
