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
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('username'); // e.g. budi
            $table->string('email')->unique(); // e.g. budi@perusahaan.com
            $table->string('display_name'); // e.g. Budi Pratama
            $table->string('password'); // Password hash (Bcrypt / Argon2)
            
            // Storage Quota
            $table->unsignedBigInteger('quota_bytes')->default(10737418240); // 10 GB
            $table->unsignedBigInteger('used_bytes')->default(0);

            // Privileges & Status
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);

            // User Preferences
            $table->text('signature')->nullable();
            $table->boolean('auto_reply_active')->default(false);
            $table->string('auto_reply_subject')->nullable();
            $table->text('auto_reply_body')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
