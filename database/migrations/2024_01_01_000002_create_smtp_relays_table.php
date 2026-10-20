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
        Schema::create('smtp_relays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->string('name')->default('Brevo SMTP Relay');
            $table->string('provider')->default('brevo'); // brevo, ses, mailgun, custom
            $table->string('host')->default('smtp-relay.brevo.com');
            $table->integer('port')->default(587);
            $table->string('encryption')->nullable()->default('tls'); // tls, ssl, null
            $table->string('username')->nullable(); // e.g. login email or key id
            $table->text('password')->nullable(); // Master key / API SMTP key
            $table->string('sender_email')->nullable(); // e.g. no-reply@company.com
            $table->string('sender_name')->nullable()->default('ApexMail');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(true);

            // Health / Diagnostic Check
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status')->nullable(); // success, failed
            $table->text('last_test_message')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_relays');
    }
};
