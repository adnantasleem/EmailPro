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
        // Contact Lists - reusable lists of contacts
        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('contacts_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // Contacts - emails in contact lists
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_list_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('name')->nullable();
            $table->json('custom_fields')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('validation_status')->default('pending');
            $table->json('validation_result')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->string('validation_error')->nullable();
            $table->timestamps();

            $table->unique(['contact_list_id', 'email']);
            $table->index('email');
            $table->index('validation_status');
        });

        // Global unsubscribe list per user
        Schema::create('unsubscribes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('reason')->nullable();
            $table->timestamp('unsubscribed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'email']);
            $table->index('email');
        });

        // Pivot table for campaign <-> contact list
        Schema::create('campaign_contact_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_list_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_list_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_contact_list');
        Schema::dropIfExists('unsubscribes');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_lists');
    }
};
