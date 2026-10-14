<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('gemini_api_key')->nullable()->after('anthropic_api_key');
        });

        Schema::table('runs', function (Blueprint $table) {
            $table->string('engine')->default('claude_code')->after('status');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->string('engine')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('settings', fn (Blueprint $table) => $table->dropColumn('gemini_api_key'));
        Schema::table('runs', fn (Blueprint $table) => $table->dropColumn('engine'));
        Schema::table('assignments', fn (Blueprint $table) => $table->dropColumn('engine'));
    }
};
