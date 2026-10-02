<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    private function check(Request $r): void
    {
        abort_unless($r->user()->role === 'admin', 403);
    }

    public function users(Request $r)
    {
        $this->check($r);
        $search = $r->validate(['search' => 'nullable|string|max:100']);

        return response()->json(User::query()->when($search['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))->select(['id', 'name', 'email', 'role', 'suspended_at', 'created_at'])->orderBy('id')->paginate(20));
    }

    public function status(Request $r, User $user)
    {
        $this->check($r);
        $data = $r->validate(['suspended' => 'required|boolean']);
        DB::transaction(function () use ($r, $user, $data) {
            // Serialize administrative state transitions to preserve the last active admin.
            User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($data['suspended']) {
                abort_if($user->id === $r->user()->id, 422, 'You cannot suspend yourself.');
                abort_if($user->role === 'admin' && ! $user->suspended_at && User::where('role', 'admin')->whereNull('suspended_at')->count() <= 1, 422, 'The last active admin must remain active.');
            }
            $user->suspended_at = $data['suspended'] ? now() : null;
            $user->save();
            DB::table('audit_events')->insert(['actor_id' => $r->user()->id, 'target_id' => $user->id, 'action' => $data['suspended'] ? 'suspend' : 'reactivate', 'created_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Account updated.']);
    }

    public function health(Request $r)
    {
        $this->check($r);
        try {
            DB::select('SELECT 1');
            $db = 'available';
        } catch (\Throwable) {
            $db = 'unavailable';
        }

return response()->json(['database' => $db, 'ai_configured' => (bool) config('services.openai.api_key'), 'storage_writable' => is_writable(storage_path('app'))]);
    }

    public function audit(Request $r)
    {
        $this->check($r);

        return response()->json(DB::table('audit_events')->orderByDesc('id')->paginate(20));
    }
}
