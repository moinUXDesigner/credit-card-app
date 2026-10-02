<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\User;
use App\Services\SyncEventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SharingController extends Controller
{
    public function index(Card $card)
    {
        $this->authorize('delete', $card);

        return response()->json(DB::table('card_memberships')->join('users', 'users.id', '=', 'card_memberships.user_id')->where('card_id', $card->id)->select('card_memberships.*', 'users.name', 'users.email')->get());
    }

    public function invite(Request $r, Card $card)
    {
        $this->authorize('delete', $card);
        $d = $r->validate(['email' => 'required|email', 'permission' => 'required|in:viewer,editor']);
        $user = User::where('email', $d['email'])->first();
        abort_unless($user, 422, 'Invite an existing registered user.');
        abort_if($user->id === $card->user_id, 422, 'The owner already has access.');
        DB::transaction(function () use ($card, $user, $d) {
            $card->refresh();
            DB::table('card_memberships')->updateOrInsert(['card_id' => $card->id, 'user_id' => $user->id], ['permission' => $d['permission'], 'accepted_at' => null, 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
            app(SyncEventService::class)->visibility($card, $user->id, 'revoke');
        });

        return response()->json(['message' => 'Invitation sent. Acceptance is required.'], 201);
    }

    public function invitations(Request $r)
    {
        return response()->json(DB::table('card_memberships')->join('cards', 'cards.id', '=', 'card_memberships.card_id')->where('card_memberships.user_id', $r->user()->id)->whereNull('accepted_at')->where('expires_at', '>', now())->select('card_memberships.*', 'cards.card_name')->get());
    }

    public function accept(Request $r, int $invitation)
    {
        DB::transaction(function () use ($r, $invitation) {
            $m = DB::table('card_memberships')->where('id', $invitation)->where('user_id', $r->user()->id)->lockForUpdate()->first();
            abort_unless($m && ! $m->accepted_at && now()->lt($m->expires_at), 404, 'Invitation expired or unavailable.');
            DB::table('card_memberships')->where('id', $m->id)->update(['accepted_at' => now(), 'updated_at' => now()]);
            app(SyncEventService::class)->visibility(Card::findOrFail($m->card_id), $r->user()->id, 'grant');
        });

        return response()->json(['message' => 'Invitation accepted.']);
    }

    public function update(Request $r, Card $card, int $member)
    {
        $this->authorize('delete', $card);
        $d = $r->validate(['permission' => 'required|in:viewer,editor']);
        $m = DB::table('card_memberships')->where('card_id', $card->id)->where('id', $member)->first();
        abort_unless($m, 404);
        DB::transaction(function () use ($card, $m, $d) {
            DB::table('card_memberships')->where('id', $m->id)->update(['permission' => $d['permission'], 'updated_at' => now()]);
            app(SyncEventService::class)->visibility($card, $m->user_id, 'grant');
        });

        return response()->json(['message' => 'Permission updated.']);
    }

    public function destroy(Card $card, int $member)
    {
        $this->authorize('delete', $card);
        $m = DB::table('card_memberships')->where('card_id', $card->id)->where('id', $member)->first();
        abort_unless($m, 404);
        DB::transaction(function () use ($card, $m) {
            DB::table('card_memberships')->where('id', $m->id)->delete();
            app(SyncEventService::class)->visibility($card,$m->user_id,'revoke');
        });

        return response()->json(null,204);
    }
}
