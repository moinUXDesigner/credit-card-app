<?php

namespace App\Policies;

use App\Models\Statement;
use App\Models\User;

class StatementPolicy
{
    public function view(User $user, Statement $statement): bool
    {
        return $user->id === $statement->card->user_id;
    }

    public function update(User $user, Statement $statement): bool
    {
        return $user->id === $statement->card->user_id;
    }

    public function delete(User $user, Statement $statement): bool
    {
        return $user->id === $statement->card->user_id;
    }
}
