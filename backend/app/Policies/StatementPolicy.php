<?php

namespace App\Policies;

use App\Models\Statement;
use App\Models\User;

class StatementPolicy
{
    public function view(User $user, Statement $statement): bool
    {
        return in_array($statement->card->permissionFor($user), ['owner', 'editor', 'viewer'], true);
    }

    public function update(User $user, Statement $statement): bool
    {
        return in_array($statement->card->permissionFor($user), ['owner', 'editor'], true);
    }

    public function delete(User $user, Statement $statement): bool
    {
        return in_array($statement->card->permissionFor($user), ['owner', 'editor'], true);
    }
}
