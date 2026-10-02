<?php

namespace App\Services;

use App\Models\Card;
use Illuminate\Support\Facades\DB;

class SyncEventService
{
    public function visibility(Card $card, int $userId, string $action): void
    {
        DB::table('sync_events')->insert(['user_id' => $userId, 'entity' => 'cards', 'entity_id' => $card->id, 'action' => $action, 'revision' => $card->revision, 'created_at' => now()]);
    }

    public function record($model, string $action): void
    {
        $card = $model instanceof Card ? $model : $model->card;
        if (! $card) {
            return;
        }
        $ids = DB::table('card_memberships')->where('card_id', $card->id)->whereNotNull('accepted_at')->pluck('user_id')->push($card->user_id)->unique();
        foreach ($ids as $id) {
            DB::table('sync_events')->insert(['user_id' => $id, 'entity' => $model->getTable(), 'entity_id' => $model->id, 'action' => $action, 'revision' => $model->revision, 'created_at' => now()]);
        }
    }
}
