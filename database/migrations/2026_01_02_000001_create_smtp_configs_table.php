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
        Schema::create('smtp_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(587);
            $table->string('username');
            $table->string('password');
            $table->enum('encryption', ['tls', 'ssl', 'none'])->default('tls');
            $table->string('from_email');
            $table->string('from_name');
            $table->integer('daily_limit')->default(500);
            $table->integer('sent_today')->default(0);
            $table->date('last_reset_date')->nullable();
            $table->boolean('is_active')->default(true);
            // Warmup fields
            $table->boolean('is_warming_up')->default(false);
            $table->date('warmup_started_at')->nullable();
            $table->integer('warmup_day')->default(0);
            $table->integer('warmup_daily_limit')->default(20);
            // Bounce tracking fields
            $table->integer('total_sent')->default(0);
            $table->integer('total_bounced')->default(0);
            $table->integer('sent_last_hour')->default(0);
            $table->integer('bounced_last_hour')->default(0);
            $table->timestamp('last_hour_reset')->nullable();
            $table->decimal('bounce_rate', 5, 2)->default(0);
            $table->boolean('auto_paused')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_configs');
    }
};
