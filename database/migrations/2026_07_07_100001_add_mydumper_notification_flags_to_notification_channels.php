<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notification_channels', 'notify_on_upload_complete')) {
            Schema::table('notification_channels', function (Blueprint $table) {
                $table->boolean('notify_on_upload_complete')->default(false)->after('notify_on_failure');
            });
        }

        if (! Schema::hasColumn('notification_channels', 'notify_on_verification_failed')) {
            Schema::table('notification_channels', function (Blueprint $table) {
                $table->boolean('notify_on_verification_failed')->default(true)->after('notify_on_upload_complete');
            });
        }
    }

    public function down(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropColumn(['notify_on_upload_complete', 'notify_on_verification_failed']);
        });
    }
};
