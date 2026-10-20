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
        Schema::create('mail_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $table->string('name'); // e.g. Inbox, Sent, Drafts, Trash, Spam, Archive
            $table->string('slug'); // inbox, sent, drafts, trash, spam, archive
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('mail_folders')->nullOnDelete();
            $table->boolean('is_system')->default(false); // Can't be deleted if system folder
            $table->integer('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['mail_account_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_folders');
    }
};
