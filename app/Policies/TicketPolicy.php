<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TicketPolicy
{
    use HandlesAuthorization;

    public function create(User $user): bool
    {
        $sub = $user->activeSubscription;
        if (! $sub || ! $sub->isActive()) {
            return false;
        }

        $planLimit = $sub->plan?->ticket_limit ?? 0;
        $used = $sub->ticketsCreatedThisPeriod();
        return $used < $planLimit;
    }
}
