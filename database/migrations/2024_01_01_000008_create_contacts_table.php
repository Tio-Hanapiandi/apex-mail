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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->nullable()->constrained('mail_accounts')->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained('domains')->cascadeOnDelete();
            
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->text('notes')->nullable();
            $table->string('avatar')->nullable();
            
            // Global Address List flag (organization directory)
            $table->boolean('is_gal')->default(false);

            $table->timestamps();

            $table->index(['domain_id', 'is_gal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
