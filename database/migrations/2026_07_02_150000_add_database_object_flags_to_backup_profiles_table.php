<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_profiles', function (Blueprint $table) {
            $table->boolean('include_stored_procedures')->default(false)->after('backup_folders');
            $table->boolean('include_views')->default(false)->after('include_stored_procedures');
        });
    }

    public function down(): void
    {
        Schema::table('backup_profiles', function (Blueprint $table) {
            $table->dropColumn(['include_stored_procedures', 'include_views']);
        });
    }
};
