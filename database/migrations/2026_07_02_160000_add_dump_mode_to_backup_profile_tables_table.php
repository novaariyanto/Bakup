<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_profile_tables', function (Blueprint $table) {
            $table->string('dump_mode')->default('structure_only')->after('table_name');
        });

        DB::table('backup_profile_tables')->update([
            'dump_mode' => 'structure_only',
        ]);
    }

    public function down(): void
    {
        Schema::table('backup_profile_tables', function (Blueprint $table) {
            $table->dropColumn('dump_mode');
        });
    }
};
