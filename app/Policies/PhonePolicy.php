<?php

namespace App\Policies;

use App\Models\User;

class PhonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-phones');
    }

    public function view(User $user, $model): bool
    {
        return $user->hasPermissionTo('view-phones');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-phones');
    }

    public function update(User $user, $model): bool
    {
        return $user->hasPermissionTo('edit-phones');
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasPermissionTo('delete-phones');
    }
}
