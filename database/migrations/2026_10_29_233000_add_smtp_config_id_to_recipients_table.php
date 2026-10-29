<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->foreignId('smtp_config_id')->nullable()->after('campaign_id')->constrained('smtp_configs')->nullOnDelete();
            $table->index(['campaign_id', 'smtp_config_id']);
        });

        // Backfill smtp_config_id from existing email_logs if available
        try {
            DB::statement("
                UPDATE recipients r
                JOIN email_logs el ON el.recipient_id = r.id
                SET r.smtp_config_id = el.smtp_config_id
                WHERE r.smtp_config_id IS NULL
            ");
        } catch (\Throwable $e) {
            // Silently continue if email_logs table is empty or update fails
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropForeign(['smtp_config_id']);
            $table->dropIndex(['campaign_id', 'smtp_config_id']);
            $table->dropColumn('smtp_config_id');
        });
    }
};
