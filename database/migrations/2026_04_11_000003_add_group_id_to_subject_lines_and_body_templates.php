<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_lines', function (Blueprint $table) {
            $table->foreignId('subject_group_id')->nullable()->after('campaign_id')
                  ->constrained('subject_groups')->onDelete('set null');
        });

        Schema::table('body_templates', function (Blueprint $table) {
            $table->foreignId('body_group_id')->nullable()->after('campaign_id')
                  ->constrained('body_groups')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('subject_lines', function (Blueprint $table) {
            $table->dropForeign(['subject_group_id']);
            $table->dropColumn('subject_group_id');
        });

        Schema::table('body_templates', function (Blueprint $table) {
            $table->dropForeign(['body_group_id']);
            $table->dropColumn('body_group_id');
        });
    }
};
