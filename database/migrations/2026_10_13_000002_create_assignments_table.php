<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cmid')->unique();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->string('name');
            $table->string('course');
            $table->string('module');
            $table->timestamp('due_at')->nullable();
            $table->boolean('overdue')->default(false);
            $table->string('url');
            $table->longText('statement_md')->nullable();
            $table->longText('statement_html')->nullable();
            $table->text('summary')->nullable();
            $table->longText('review_notes')->nullable();
            $table->string('status')->index();
            $table->string('model')->nullable();
            $table->string('effort')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('last_run_id')->nullable();
            $table->timestamps();
        });

        Schema::create('assignment_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('path');
            $table->longText('content')->nullable();
            $table->string('disk_path')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'kind', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_files');
        Schema::dropIfExists('assignments');
    }
};
