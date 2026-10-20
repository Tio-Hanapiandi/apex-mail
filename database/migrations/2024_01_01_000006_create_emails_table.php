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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->foreignId('mail_folder_id')->constrained('mail_folders')->cascadeOnDelete();

            // Identifiers & Threading
            $table->string('message_id')->nullable()->index();
            $table->string('in_reply_to')->nullable();
            $table->string('thread_id')->nullable()->index();

            // Sender & Recipients
            $table->string('from_name')->nullable();
            $table->string('from_email');
            $table->text('to'); // JSON array: [{"name": "Ahmad", "email": "ahmad@..."}, ...]
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->string('reply_to')->nullable();

            // Content
            $table->string('subject')->nullable();
            $table->string('snippet', 255)->nullable(); // 1-line preview for listing
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            // Flags
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->boolean('has_attachments')->default(false);

            // Timestamps & Metrics
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
