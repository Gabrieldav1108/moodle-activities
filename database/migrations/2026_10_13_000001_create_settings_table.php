<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->json('run_times');
            $table->json('weekdays');
            $table->string('model');
            $table->string('effort');
            $table->unsignedSmallInteger('days_back')->default(30);
            $table->unsignedSmallInteger('days_ahead')->default(120);
            $table->string('moodle_url');
            $table->string('moodle_username')->nullable();
            $table->text('moodle_password')->nullable();
            $table->text('anthropic_api_key')->nullable();
            $table->timestamp('worker_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
