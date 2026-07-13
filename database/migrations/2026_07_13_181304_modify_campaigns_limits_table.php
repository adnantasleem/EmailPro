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
            $table->dropColumn(['emails_per_hour', 'min_delay_seconds', 'max_delay_seconds']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->integer('emails_per_hour')->default(100);
            $table->integer('min_delay_seconds')->default(10);
            $table->integer('max_delay_seconds')->default(30);
        });
    }
};
