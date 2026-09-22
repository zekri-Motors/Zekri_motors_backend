<?php

namespace App\Policies;

use App\Models\CarMedia;
use App\Models\User;

class CarMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('car_media.view');
    }

    public function view(User $user, CarMedia $carMedia): bool
    {
        return $user->can('car_media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('car_media.create');
    }

    public function update(User $user, CarMedia $carMedia): bool
    {
        return $user->can('car_media.update');
    }

    public function delete(User $user, CarMedia $carMedia): bool
    {
        return $user->can('car_media.delete');
    }
}
