<?php

namespace App\Policies;

use App\Models\Resume;
use App\Models\User;

/**
 * Personal data: only the owner may see or change it.
 */
class ResumePolicy
{
    public function view(User $user, Resume $model): bool
    {
        return $user->id === $model->user_id;
    }

    public function update(User $user, Resume $model): bool
    {
        return $user->id === $model->user_id;
    }

    public function delete(User $user, Resume $model): bool
    {
        return $user->id === $model->user_id;
    }
}
