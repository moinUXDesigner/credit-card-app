<?php

namespace App\Policies;

use App\Models\Benefit;
use App\Models\User;

class BenefitPolicy
{
    public function view(User $user, Benefit $benefit): bool
    {
        return in_array($benefit->card->permissionFor($user), ['owner', 'editor', 'viewer'], true);
    }

    public function update(User $user, Benefit $benefit): bool
    {
        return in_array($benefit->card->permissionFor($user), ['owner', 'editor'], true);
    }

    public function delete(User $user, Benefit $benefit): bool
    {
        return in_array($benefit->card->permissionFor($user), ['owner', 'editor'], true);
    }
}
