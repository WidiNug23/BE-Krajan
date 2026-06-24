<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
   protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
{
    // hapus akun unverified lebih dari 1 hari
    $schedule->command('masyarakat:delete-unverified')->daily();

        // hapus otp expired/used tiap 10 menit
    $schedule->command('otp:delete-expired')->everyTenMinutes();
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
