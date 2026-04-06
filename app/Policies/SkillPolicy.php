<?php

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SkillPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function update(User $user, Skill $skill): bool
    {
        return $user->id === $skill->user_id;
    }

    public function delete(User $user, Skill $skill): bool
    {
        return $user->id === $skill->user_id;
    }
}
