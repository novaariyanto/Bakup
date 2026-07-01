<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_profiles', function (Blueprint $table) {
            $table->timestamp('next_run_at')->nullable()->after('is_active');
            $table->timestamp('last_scheduled_run_at')->nullable()->after('next_run_at');

            $table->index(['is_active', 'schedule_type', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::table('backup_profiles', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'schedule_type', 'next_run_at']);
            $table->dropColumn(['next_run_at', 'last_scheduled_run_at']);
        });
    }
};
