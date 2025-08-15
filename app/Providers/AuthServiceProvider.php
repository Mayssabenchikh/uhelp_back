<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Map des modèles -> policies
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Ticket::class => \App\Policies\TicketPolicy::class,
        // ajouter d'autres mappings si besoin
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Ici tu peux définir des Gates si nécessaire :
        // Gate::define('manage-users', fn($user) => $user->isAdmin());
    }
}
