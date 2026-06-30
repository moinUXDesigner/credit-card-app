<?php

namespace App\Policies;

use App\Models\Benefit;
use App\Models\User;

class BenefitPolicy
{
    public function view(User $user, Benefit $benefit): bool
    {
        return $user->id === $benefit->card->user_id;
    }

    public function update(User $user, Benefit $benefit): bool
    {
        return $user->id === $benefit->card->user_id;
    }

    public function delete(User $user, Benefit $benefit): bool
    {
        return $user->id === $benefit->card->user_id;
    }
}
