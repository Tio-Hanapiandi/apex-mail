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
        Schema::table('emails', function (Blueprint $table) {
            $table->string('delivery_status')->default('delivered')->index()->after('has_attachments');
            $table->text('delivery_error')->nullable()->after('delivery_status');
            $table->timestamp('queued_at')->nullable()->after('delivery_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'delivery_error', 'queued_at']);
        });
    }
};
