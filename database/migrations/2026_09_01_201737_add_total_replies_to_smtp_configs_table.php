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
            $table->integer('total_replies')->default(0)->after('total_bounced');
        });
        
        Schema::table('recipients', function (Blueprint $table) {
            $table->timestamp('replied_at')->nullable()->after('validated_at');
            $table->index('replied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_configs', function (Blueprint $table) {
            $table->dropColumn('total_replies');
        });
        
        Schema::table('recipients', function (Blueprint $table) {
            $table->dropIndex(['replied_at']);
            $table->dropColumn('replied_at');
        });
    }
};
