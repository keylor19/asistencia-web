<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    /**
     * Determina si el docente tiene asignado este grupo.
     */
    public function access(User $user, Group $group): bool
    {
        return $user->groups()->where('student_groups.id', $group->id)->exists();
    }
}
