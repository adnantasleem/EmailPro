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
            $table->integer('min_emails_per_hour')->nullable()->default(null)->change();
            $table->integer('max_emails_per_hour')->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_configs', function (Blueprint $table) {
            $table->integer('min_emails_per_hour')->default(20)->nullable(false)->change();
            $table->integer('max_emails_per_hour')->default(50)->nullable(false)->change();
        });
    }
};
