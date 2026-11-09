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
        Schema::table('contact_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('contact_lists', 'has_pending_contacts')) {
                $table->boolean('has_pending_contacts')->default(false)->after('total_contacts')->index();
            }
        });

        // 2. Add composite performance indexes to contacts
        Schema::table('contacts', function (Blueprint $table) {
            // Check if composite index already exists before adding
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('contacts'));

            if (!in_array('idx_contacts_list_status_id', $indexes)) {
                $table->index(['contact_list_id', 'validation_status', 'id'], 'idx_contacts_list_status_id');
            }
        });

        // 3. Add composite performance index to recipients
        Schema::table('recipients', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('recipients'));

            if (!in_array('idx_recipients_campaign_status_id', $indexes)) {
                $table->index(['campaign_id', 'status', 'id'], 'idx_recipients_campaign_status_id');
            }
        });

        // 4. Add index to email_logs
        Schema::table('email_logs', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('email_logs'));

            if (!in_array('idx_logs_campaign_status', $indexes)) {
                $table->index(['campaign_id', 'status'], 'idx_logs_campaign_status');
            }
        });

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
