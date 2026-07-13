<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('smtp_configs', function (Blueprint $table) {
            $table->string('pacing_strategy')->default('per_hour')->after('daily_limit');
            $table->integer('min_emails_per_day')->nullable()->after('pacing_strategy');
            $table->integer('max_emails_per_day')->nullable()->after('min_emails_per_day');
            $table->integer('current_daily_limit')->nullable()->after('max_emails_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_configs', function (Blueprint $table) {
            $table->dropColumn([
                'pacing_strategy',
                'min_emails_per_day',
                'max_emails_per_day',
                'current_daily_limit',
            ]);
        });
    }
};

