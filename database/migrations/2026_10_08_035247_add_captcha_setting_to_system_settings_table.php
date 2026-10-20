<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = Carbon::now();
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'login_captcha_enabled'],
            [
                'value' => '1',
                'description' => 'Aktifkan verifikasi CAPTCHA pada halaman login Webmail dan Admin Console',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')->where('key', 'login_captcha_enabled')->delete();
    }
};
