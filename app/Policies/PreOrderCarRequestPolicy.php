<?php

namespace App\Policies;

use App\Models\PreOrderCarRequest;
use App\Models\User;

class PreOrderCarRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pre_order_car_requests.view');
    }

    public function view(User $user, PreOrderCarRequest $preOrderCarRequest): bool
    {
        return $user->can('pre_order_car_requests.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pre_order_car_requests.create');
    }

    public function update(User $user, PreOrderCarRequest $preOrderCarRequest): bool
    {
        return $user->can('pre_order_car_requests.update');
    }

    public function delete(User $user, PreOrderCarRequest $preOrderCarRequest): bool
    {
        return $user->can('pre_order_car_requests.delete');
    }
}
