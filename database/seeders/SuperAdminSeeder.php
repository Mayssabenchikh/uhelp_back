<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Vérifie si un Super Admin existe déjà
        $existing = User::where('role', 'super_admin')->first();
        if ($existing) {
            $this->command->info('Super Admin already exists. Skipping.');
            return;
        }

        // Créer le Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password123'), // changer mot de passe
            'role' => 'super_admin',
            'phone_number' => null,
            'location' => null,
        ]);

        // Envoyer l'email de vérification
        $superAdmin->sendEmailVerificationNotification();

        $this->command->info('Super Admin created and verification email sent.');
    }
}
