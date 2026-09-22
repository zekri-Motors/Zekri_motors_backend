<?php

namespace App\Policies;

use App\Models\GeneralMedia;
use App\Models\User;

class GeneralMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('general_media.view');
    }

    public function view(User $user, GeneralMedia $generalMedia): bool
    {
        return $user->can('general_media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('general_media.create');
    }

    public function update(User $user, GeneralMedia $generalMedia): bool
    {
        return $user->can('general_media.update');
    }

    public function delete(User $user, GeneralMedia $generalMedia): bool
    {
        return $user->can('general_media.delete');
    }
}
