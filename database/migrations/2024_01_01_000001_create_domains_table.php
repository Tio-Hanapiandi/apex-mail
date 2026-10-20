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
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g. company.com
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(true);
            $table->string('verification_token')->nullable();
            
            // DNS & Authentication Records
            $table->string('dkim_selector')->default('apexmail');
            $table->text('dkim_private_key')->nullable();
            $table->text('dkim_public_key')->nullable();
            $table->text('spf_record')->nullable();
            $table->text('dmarc_record')->nullable();
            $table->text('mx_record')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
