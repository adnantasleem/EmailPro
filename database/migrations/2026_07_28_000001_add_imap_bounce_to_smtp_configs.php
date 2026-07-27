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
            $table->string('imap_host')->nullable()->after('password');
            $table->integer('imap_port')->nullable()->after('imap_host');
            $table->string('imap_encryption')->nullable()->after('imap_port');
            $table->string('imap_folder')->nullable()->default('INBOX')->after('imap_encryption');
            $table->boolean('bounce_check_enabled')->default(false)->after('imap_folder');
            $table->timestamp('last_bounce_check_at')->nullable()->after('bounce_check_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_configs', function (Blueprint $table) {
            $table->dropColumn([
                'imap_host',
                'imap_port',
                'imap_encryption',
                'imap_folder',
                'bounce_check_enabled',
                'last_bounce_check_at'
            ]);
        });
    }
};
