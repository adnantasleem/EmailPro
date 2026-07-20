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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('daily_email_limit')->nullable()->after('monthly_email_limit');
            $table->integer('yearly_email_limit')->nullable()->after('daily_email_limit');
            $table->timestamp('expires_at')->nullable()->after('yearly_email_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_email_limit', 'yearly_email_limit', 'expires_at']);
        });
    }
};
