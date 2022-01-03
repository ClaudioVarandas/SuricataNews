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
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        if($this->app->isProduction()){
            // Covid19 PT
            $schedule->command('covid19pt:daily-update')->everyFiveMinutes();
            $schedule->command('covid19pt:county-update')->everyFiveMinutes();
            // Weather
            $schedule->command('weather:ipma:fetch-warnings')->dailyAt('08:30');
            // GAMES OUTBREAK
            $schedule->command('go:news:gamespot')->everyFifteenMinutes();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load([
            __DIR__ . '/Commands/Covid19PT',
            //__DIR__.'/Commands/Weather'
            __DIR__ . '/Commands/Weather/Ipma',
            __DIR__ . '/Commands/GamesOutbreak'
        ]);

        require base_path('routes/console.php');
    }
}
