<?php

namespace App\Policies;

use App\Models\ApplicationEmail;
use App\Models\User;

/**
 * Personal data: only the owner may see or change it.
 */
class ApplicationEmailPolicy
{
    public function view(User $user, ApplicationEmail $model): bool
    {
        return $user->id === $model->user_id;
    }

    public function update(User $user, ApplicationEmail $model): bool
    {
        return $user->id === $model->user_id;
    }

    public function delete(User $user, ApplicationEmail $model): bool
    {
        return $user->id === $model->user_id;
    }
}
