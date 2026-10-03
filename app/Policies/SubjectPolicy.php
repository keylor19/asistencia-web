<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\User;

class SubjectPolicy
{
    /**
     * Determina si la subárea le pertenece al docente autenticado.
     */
    public function access(User $user, Subject $subject): bool
    {
        return $subject->user_id === $user->id;
    }
}
