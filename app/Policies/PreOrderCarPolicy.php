<?php

namespace App\Policies;

use App\Models\PreOrderCar;
use App\Models\User;

class PreOrderCarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pre_order_cars.view');
    }

    public function view(User $user, PreOrderCar $preOrderCar): bool
    {
        return $user->can('pre_order_cars.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pre_order_cars.create');
    }

    public function update(User $user, PreOrderCar $preOrderCar): bool
    {
        return $user->can('pre_order_cars.update');
    }

    public function delete(User $user, PreOrderCar $preOrderCar): bool
    {
        return $user->can('pre_order_cars.delete');
    }
}
