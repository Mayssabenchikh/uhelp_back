<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Définir les commandes programmées.
     */
    protected function schedule(Schedule $schedule)
    {
        // Exécute la commande subscriptions:expire tous les jours
        $schedule->command('subscriptions:expire')->daily();
    }

    /**
     * Enregistrer les commandes Artisan.
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
