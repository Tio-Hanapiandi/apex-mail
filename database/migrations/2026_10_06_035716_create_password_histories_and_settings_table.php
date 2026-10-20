<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_account_id')->constrained('mail_accounts')->onDelete('cascade');
            $table->string('password_hash');
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $now = Carbon::now();
        DB::table('system_settings')->insert([
            [
                'key' => 'password_min_length',
                'value' => '8',
                'description' => 'Panjang minimal karakter untuk kata sandi',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'password_require_uppercase',
                'value' => '1',
                'description' => 'Wajib menyertakan huruf kapital / besar (A-Z)',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'password_require_symbol',
                'value' => '1',
                'description' => 'Wajib menyertakan simbol unik / karakter khusus',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'password_prevent_reuse',
                'value' => '1',
                'description' => 'Mencegah penggunaan kata sandi yang sama dengan sebelumnya',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'password_history_limit',
                'value' => '5',
                'description' => 'Jumlah riwayat kata sandi sebelumnya yang dicegah untuk digunakan kembali',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('system_settings');
    }
};
