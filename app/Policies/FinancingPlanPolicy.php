<?php

namespace App\Policies;

use App\Models\User;

class FinancingPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-financing-plans');
    }

    public function view(User $user, $model): bool
    {
        return $user->hasPermissionTo('view-financing-plans');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-financing-plans');
    }

    public function update(User $user, $model): bool
    {
        return $user->hasPermissionTo('edit-financing-plans');
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasPermissionTo('delete-financing-plans');
    }
}
