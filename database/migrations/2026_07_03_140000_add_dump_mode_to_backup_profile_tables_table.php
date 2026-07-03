<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('backup_profile_tables', 'dump_mode')) {
            Schema::table('backup_profile_tables', function (Blueprint $table) {
                $table->string('dump_mode')->default('structure_only')->after('table_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('backup_profile_tables', 'dump_mode')) {
            Schema::table('backup_profile_tables', function (Blueprint $table) {
                $table->dropColumn('dump_mode');
            });
        }
    }
};
