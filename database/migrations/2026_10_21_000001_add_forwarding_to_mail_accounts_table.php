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
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->boolean('forward_active')->default(false)->after('auto_reply_body');
            $table->string('forward_destination')->nullable()->after('forward_active');
            $table->boolean('forward_keep_copy')->default(true)->after('forward_destination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->dropColumn(['forward_active', 'forward_destination', 'forward_keep_copy']);
        });
    }
};
