<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Comment réinitialiser mon mot de passe ?',
                'answer' => 'Cliquez sur "Mot de passe oublié" sur la page de connexion et suivez les instructions envoyées par email.',
                'language' => 'fr',
                'category' => 'auth',
                'is_active' => true,
            ],
            [
                'question' => 'How do I reset my password?',
                'answer' => 'Click "Forgot password" on the sign-in page and follow the email instructions.',
                'language' => 'en',
                'category' => 'auth',
                'is_active' => true,
            ],
            [
                'question' => 'Comment créer un ticket ?',
                'answer' => 'Allez dans le tableau de bord, cliquez sur "Nouveau ticket" et remplissez le formulaire.',
                'language' => 'fr',
                'category' => 'support',
                'is_active' => true,
            ],
            [
                'question' => 'What are the supported file types for attachments?',
                'answer' => 'We accept JPG, PNG, PDF and TXT files. Max size 10MB.',
                'language' => 'en',
                'category' => 'attachments',
                'is_active' => true,
            ],
            [
                'question' => 'Comment contacter le support ?',
                'answer' => 'Vous pouvez ouvrir un ticket depuis l\'application ou envoyer un email à support@example.com.',
                'language' => 'fr',
                'category' => 'support',
                'is_active' => true,
            ],
            [
                'question' => 'How do I change my subscription?',
                'answer' => 'Go to Billing > Subscriptions in your account and choose upgrade or cancel.',
                'language' => 'en',
                'category' => 'billing',
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $f) {
            Faq::create($f);
        }
    }
}
