<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );

        // Génère un token si non déjà généré
        $token = $admin->createToken('AdminSeederToken')->plainTextToken;

        // Affiche dans le terminal
        echo "\nAdmin Token: " . $token . "\n";
    }
}
