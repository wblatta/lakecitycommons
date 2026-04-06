<?php

namespace App\Policies;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SkillPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'admin' ? true : null;
    }

    public function update(User $user, Skill $skill): bool
    {
        return $user->id === $skill->user_id;
    }

    public function delete(User $user, Skill $skill): bool
    {
        return $user->id === $skill->user_id;
    }
}
