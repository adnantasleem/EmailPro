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
        // 1. Add has_pending_contacts flag to contact_lists
        if (!Schema::hasColumn('contact_lists', 'has_pending_contacts')) {
            Schema::table('contact_lists', function (Blueprint $table) {
                $table->boolean('has_pending_contacts')->default(false)->index();
            });
        }

        // 2. Add composite performance indexes to contacts
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $table->index(['contact_list_id', 'validation_status', 'id'], 'idx_contacts_list_status_id');
            });
        } catch (\Throwable $e) {
            // Index already exists
        }

        // 3. Add composite performance index to recipients
        try {
            Schema::table('recipients', function (Blueprint $table) {
                $table->index(['campaign_id', 'status', 'id'], 'idx_recipients_campaign_status_id');
            });
        } catch (\Throwable $e) {
            // Index already exists
        }

        // 4. Add index to email_logs
        try {
            Schema::table('email_logs', function (Blueprint $table) {
                $table->index(['campaign_id', 'status'], 'idx_logs_campaign_status');
            });
        } catch (\Throwable $e) {
            // Index already exists
        }

        // 5. Initialize has_pending_contacts flag for existing lists
        try {
            DB::statement("
                UPDATE contact_lists
                SET has_pending_contacts = (
                    SELECT COUNT(*) > 0
                    FROM contacts
                    WHERE contacts.contact_list_id = contact_lists.id
                      AND contacts.validation_status = 'pending'
                )
            ");
        } catch (\Throwable $e) {
            // Ignore if subquery fails during setup
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_lists', function (Blueprint $table) {
            if (Schema::hasColumn('contact_lists', 'has_pending_contacts')) {
                $table->dropColumn('has_pending_contacts');
            }
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('idx_contacts_list_status_id');
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->dropIndex('idx_recipients_campaign_status_id');
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex('idx_logs_campaign_status');
        });
    }
};
