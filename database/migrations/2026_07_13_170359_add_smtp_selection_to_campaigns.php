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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('use_all_smtps')->default(true)->after('max_delay_seconds');
        });

        Schema::create('campaign_smtp_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smtp_config_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_smtp_config');

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('use_all_smtps');
        });
    }
};
