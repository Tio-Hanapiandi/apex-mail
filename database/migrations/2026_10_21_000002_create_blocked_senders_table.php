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
        Schema::create('blocked_senders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->nullable()->constrained('mail_accounts')->onDelete('cascade');
            $table->string('sender_email', 255)->index();
            $table->string('sender_domain', 255)->nullable()->index();
            $table->string('reason', 255)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('unblocked_at')->nullable();
            $table->string('unblocked_by', 100)->nullable();
            $table->timestamps();

            // Unique constraint: an account cannot block the same sender multiple times
            $table->unique(['mail_account_id', 'sender_email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_senders');
    }
};
