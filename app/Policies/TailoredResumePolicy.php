<?php

namespace App\Policies;

use App\Models\TailoredResume;
use App\Models\User;

/**
 * Personal data: only the owner may see or change it.
 */
class TailoredResumePolicy
{
    public function view(User $user, TailoredResume $model): bool
    {
        return $user->id === $model->user_id;
    }

    public function update(User $user, TailoredResume $model): bool
    {
        return $user->id === $model->user_id;
    }

    public function delete(User $user, TailoredResume $model): bool
    {
        return $user->id === $model->user_id;
    }
}
