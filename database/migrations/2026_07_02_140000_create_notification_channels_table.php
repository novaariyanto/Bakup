<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('driver');
            $table->text('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_on_success')->default(true);
            $table->boolean('notify_on_failure')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['driver', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};
