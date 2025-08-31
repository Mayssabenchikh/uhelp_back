<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ShowReverbConfig extends Command
{
    protected $signature = 'reverb:show-config';
    protected $description = 'Affiche la configuration reverb (config/broadcasting.php)';

    public function handle()
    {
        $cfg = config('broadcasting.connections.reverb');
        $this->line(json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return 0;
    }
}
