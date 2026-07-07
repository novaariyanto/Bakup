<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mydumper_export_profiles')) {
            Schema::create('mydumper_export_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('database_connection_id')->constrained()->cascadeOnDelete();
            $table->string('database')->nullable();
            $table->foreignId('storage_destination_id')->constrained('backup_destinations')->cascadeOnDelete();
            $table->string('export_type');
            $table->json('options')->nullable();
            $table->json('selected_tables')->nullable();
            $table->json('exclude_tables')->nullable();
            $table->string('output_folder')->nullable();
            $table->unsignedSmallInteger('threads')->default(4);
            $table->boolean('compression')->default(false);
            $table->string('schedule_type')->default('manual');
            $table->string('schedule_cron')->nullable();
            $table->string('schedule_time')->nullable();
            $table->unsignedTinyInteger('schedule_day_of_week')->nullable();
            $table->unsignedTinyInteger('schedule_day_of_month')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_scheduled_run_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'schedule_type', 'next_run_at']);
            });
        }

        if (! Schema::hasTable('mydumper_exports')) {
            Schema::create('mydumper_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('profile_id')->nullable()->constrained('mydumper_export_profiles')->nullOnDelete();
            $table->foreignId('connection_id')->constrained('database_connections')->cascadeOnDelete();
            $table->foreignId('storage_destination_id')->nullable()->constrained('backup_destinations')->nullOnDelete();
            $table->string('name');
            $table->string('database');
            $table->string('type');
            $table->string('status')->default('waiting');
            $table->string('current_stage')->nullable();
            $table->unsignedSmallInteger('thread')->default(4);
            $table->boolean('compression')->default(false);
            $table->string('output_path')->nullable();
            $table->text('command')->nullable();
            $table->string('log_path')->nullable();
            $table->string('metadata_path')->nullable();
            $table->unsignedBigInteger('total_size')->nullable();
            $table->unsignedInteger('file_count')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->integer('exit_code')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('current_table')->nullable();
            $table->string('current_file')->nullable();
            $table->unsignedBigInteger('rows_exported')->nullable();
            $table->unsignedInteger('tables_total')->nullable();
            $table->unsignedInteger('tables_completed')->nullable();
            $table->unsignedInteger('eta_seconds')->nullable();
            $table->unsignedInteger('process_pid')->nullable();
            $table->string('checksum')->nullable();
            $table->string('verification_status')->nullable();
            $table->text('verification_message')->nullable();
            $table->json('options_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['profile_id', 'status']);
            });
        }

        if (! Schema::hasTable('mydumper_export_logs')) {
            Schema::create('mydumper_export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_id')->constrained('mydumper_exports')->cascadeOnDelete();
            $table->string('level')->default('info');
            $table->string('stream')->default('system');
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['export_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('mydumper_export_files')) {
            Schema::create('mydumper_export_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_id')->constrained('mydumper_exports')->cascadeOnDelete();
            $table->string('relative_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('table_name')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamps();

            $table->index(['export_id', 'relative_path']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mydumper_export_files');
        Schema::dropIfExists('mydumper_export_logs');
        Schema::dropIfExists('mydumper_exports');
        Schema::dropIfExists('mydumper_export_profiles');
    }
};
