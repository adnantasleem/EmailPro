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
            $table->integer('min_emails_per_hour')->default(20)->after('daily_limit');
            $table->integer('max_emails_per_hour')->default(50)->after('min_emails_per_hour');
            $table->integer('current_hourly_limit')->default(0)->after('max_emails_per_hour');
            $table->timestamp('limit_calculated_at')->nullable()->after('current_hourly_limit');
            
            $table->time('active_time_start')->nullable()->after('limit_calculated_at');
            $table->time('active_time_end')->nullable()->after('active_time_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_configs', function (Blueprint $table) {
            $table->dropColumn([
                'min_emails_per_hour',
                'max_emails_per_hour',
                'current_hourly_limit',
                'limit_calculated_at',
                'active_time_start',
                'active_time_end'
            ]);
        });
    }
};
