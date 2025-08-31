<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateReverbKeys extends Command
{
    protected $signature = 'reverb:generate-keys {--length=32}';
    protected $description = 'Génère REVERB_APP_KEY et REVERB_APP_SECRET dans le .env (backup .env.bak)';

    public function handle()
    {
        $len = (int) $this->option('length');
        $key = bin2hex(random_bytes($len));
        $secret = bin2hex(random_bytes($len + 16));

        $envPath = base_path('.env');
        if (! File::exists($envPath)) {
            $this->error('.env not found');
            return 1;
        }

        File::copy($envPath, $envPath . '.bak');
        $env = File::get($envPath);

        if (preg_match('/^REVERB_APP_KEY=.*$/m', $env)) {
            $env = preg_replace('/^REVERB_APP_KEY=.*$/m', "REVERB_APP_KEY={$key}", $env);
        } else {
            $env .= PHP_EOL . "REVERB_APP_KEY={$key}";
        }

        if (preg_match('/^REVERB_APP_SECRET=.*$/m', $env)) {
            $env = preg_replace('/^REVERB_APP_SECRET=.*$/m', "REVERB_APP_SECRET={$secret}", $env);
        } else {
            $env .= PHP_EOL . "REVERB_APP_SECRET={$secret}";
        }

        File::put($envPath, $env);

        $this->info('Generated REVERB_APP_KEY and REVERB_APP_SECRET and saved to .env (backup .env.bak).');
        $this->line("REVERB_APP_KEY={$key}");
        $this->line("REVERB_APP_SECRET={$secret}");
        return 0;
    }
}
