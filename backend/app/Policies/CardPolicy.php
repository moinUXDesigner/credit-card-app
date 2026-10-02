<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;

class CardPolicy
{
    public function view(User $user, Card $card): bool
    {
        return in_array($card->permissionFor($user), ['owner', 'editor', 'viewer'], true);
    }

    public function update(User $user, Card $card): bool
    {
        return in_array($card->permissionFor($user), ['owner', 'editor'], true);
    }

    public function delete(User $user, Card $card): bool
    {
        return $user->id === $card->user_id;
    }
}
