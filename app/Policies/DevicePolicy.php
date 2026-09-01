<?php

namespace App\Policies;

use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-devices');
    }

    public function view(User $user, $model): bool
    {
        return $user->hasPermissionTo('view-devices');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create-devices');
    }

    public function update(User $user, $model): bool
    {
        return $user->hasPermissionTo('edit-devices');
    }

    public function delete(User $user, $model): bool
    {
        return $user->hasPermissionTo('delete-devices');
    }
}
