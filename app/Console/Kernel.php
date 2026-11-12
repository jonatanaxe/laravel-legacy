<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Runs on weekdays at 08:00 in the app timezone. In production the
        // trigger is the system cron (or the orchestrator's scheduler):
        //   * * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1
        //
        // withoutOverlapping: if the previous run is still going (slow
        // queue), the next one doesn't pile up.
        $schedule->command('tasks:due-soon')
            ->weekdays()
            ->at('08:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
