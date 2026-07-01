<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('database_connection_id')->constrained()->cascadeOnDelete();
            $table->boolean('backup_database')->default(true);
            $table->boolean('backup_folders')->default(false);
            $table->string('compression')->default('gzip');
            $table->string('schedule_type')->default('manual');
            $table->string('schedule_cron')->nullable();
            $table->time('schedule_time')->nullable();
            $table->unsignedTinyInteger('schedule_day_of_week')->nullable();
            $table->unsignedTinyInteger('schedule_day_of_month')->nullable();
            $table->string('retention_type')->default('keep_last');
            $table->unsignedInteger('retention_value')->default(7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
            $table->index('schedule_type');
        });

        Schema::create('backup_profile_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_profile_id')->constrained()->cascadeOnDelete();
            $table->string('table_name');
            $table->timestamps();

            $table->unique(['backup_profile_id', 'table_name']);
        });

        Schema::create('backup_profile_include_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_profile_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();

            $table->unique(['backup_profile_id', 'path']);
        });

        Schema::create('backup_profile_exclude_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_profile_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();

            $table->unique(['backup_profile_id', 'path']);
        });

        Schema::create('backup_profile_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('backup_destination_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['backup_profile_id', 'backup_destination_id'], 'profile_destination_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_profile_destinations');
        Schema::dropIfExists('backup_profile_exclude_folders');
        Schema::dropIfExists('backup_profile_include_folders');
        Schema::dropIfExists('backup_profile_tables');
        Schema::dropIfExists('backup_profiles');
    }
};
