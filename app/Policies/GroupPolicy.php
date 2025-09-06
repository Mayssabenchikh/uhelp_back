<?php
namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GroupPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Group $group): bool
    {
        return $group->users()->where('user_id', $user->id)->exists()
            || $group->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user !== null;
    }

    public function update(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id
            || $group->users()
                    ->where('user_id', $user->id)
                    ->wherePivot('role', 'admin')
                    ->exists();
    }

    public function delete(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id;
    }
}
