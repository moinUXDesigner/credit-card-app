<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatAccessService
{
    public function cards(User $user)
    {
        return $user->accessibleCards()->where(function ($query) use ($user) {
            $query->where('cards.user_id', $user->id)->orWhereIn('cards.id', DB::table('card_memberships')->select('card_id')->where('user_id', $user->id)->whereNotNull('accepted_at')->where('expires_at', '>', now()));
        });
    }

    public function assert(User $user, ChatConversation $conversation, bool $deleting = false): void
    {
        abort_unless($conversation->user_id === $user->id, 404);
        if ($deleting) {
            return;
        }
        $ids = array_unique(array_filter([...($conversation->referenced_card_ids ?? []), $conversation->card_id]));
        abort_if(count($ids) !== $this->cards($user)->whereIn('cards.id', $ids)->count(), 403, 'A referenced card is no longer accessible. Delete this conversation or start a new one.');
        if ($conversation->statement_id) {
            $statement = Statement::find($conversation->statement_id);
            abort_unless($statement && $this->cards($user)->whereKey($statement->card_id)->exists(), 403, 'The selected statement is no longer accessible.');
        }
    }
}
