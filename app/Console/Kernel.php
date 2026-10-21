<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Proses pengiriman ulang email tertahan setiap 10 menit
        $schedule->command('mail:process-deferred')->everyTenMinutes()->withoutOverlapping();

        // Bersihkan token password reset kadaluarsa setiap hari
        $schedule->command('auth:clear-resets')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
