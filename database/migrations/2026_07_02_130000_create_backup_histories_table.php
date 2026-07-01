<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_histories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('backup_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('filename')->nullable();
            $table->string('current_stage')->nullable();
            $table->unsignedBigInteger('original_size_bytes')->nullable();
            $table->unsignedBigInteger('compressed_size_bytes')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['backup_profile_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_history_id')->constrained()->cascadeOnDelete();
            $table->string('stage');
            $table->string('level')->default('info');
            $table->text('message');
            $table->timestamps();

            $table->index(['backup_history_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('backup_histories');
    }
};
