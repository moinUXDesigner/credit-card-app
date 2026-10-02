<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BenefitResource;
use App\Http\Resources\CardResource;
use App\Http\Resources\MonthlySpendEntryResource;
use App\Http\Resources\StatementResource;
use App\Http\Resources\UserResource;
use App\Models\Benefit;
use App\Models\Card;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SyncController extends Controller
{
    public function bootstrap(Request $r)
    {
        return $this->snapshot($r);
    }

    public function changes(Request $r)
    {
        $r->validate(['cursor' => 'required|integer|min:0']);

        return $this->snapshot($r, (int) $r->cursor);
    }

    private function snapshot(Request $r, int $cursor = 0)
    {
        // Take the cursor before reading records: racing writes may be replayed, never skipped.
        $next = (int) DB::table('sync_events')->where('user_id', $r->user()->id)->max('id');
        $cards = $r->user()->accessibleCards()->with(['benefits', 'statements.transactions', 'spendEntries'])->get();

        return response()->json(['cursor' => $next, 'events' => DB::table('sync_events')->where('user_id', $r->user()->id)->where('id', '>', $cursor)->where('id', '<=', $next)->orderBy('id')->get(), 'cards' => CardResource::collection($cards), 'benefits' => BenefitResource::collection($cards->flatMap->benefits), 'statements' => StatementResource::collection($cards->flatMap->statements), 'spend_entries' => MonthlySpendEntryResource::collection($cards->flatMap->spendEntries), 'user' => new UserResource($r->user())]);
    }

    public function mutate(Request $r)
    {
        if ($r->has('payload_json')) {
            $decoded = json_decode($r->input('payload_json'), true);
            abort_unless(is_array($decoded), 422, 'Invalid payload.');
            $r->merge(['payload' => $decoded]);
        }
        $d = $r->validate(['operation_id' => 'required|uuid', 'method' => 'required|in:POST,PUT,PATCH,DELETE', 'path' => 'required|string|max:200', 'payload' => 'nullable|array', 'revision' => 'nullable|integer|min:1', 'file' => 'nullable|file|max:15360']);
        $path = $d['path'];
        $method = $d['method'];
        $allowed = (bool) (
            ($method === 'POST' && preg_match('#^/cards(?:/import|/\d+/(?:benefits|spend-entries|statements))?$#', $path)) ||
            (in_array($method, ['PUT', 'PATCH', 'DELETE']) && preg_match('#^/(?:cards|benefits)/\d+$#', $path)) ||
            ($method === 'DELETE' && preg_match('#^/statements/\d+$#', $path)) ||
            ($method === 'POST' && preg_match('#^/benefits/\d+/mark-used$#', $path))
        );
        abort_unless($allowed, 422, 'This operation is not supported offline.');
        $payload = $d['payload'] ?? [];
        $hash = hash('sha256', json_encode([$method, $path, $payload, $d['revision'] ?? null, $r->file('file') ? hash_file('sha256', $r->file('file')->getRealPath()) : null]));

        $rollbackFiles = [];
        try {
            return DB::transaction(function () use ($r, $d, $path, $method, $payload, $hash, &$rollbackFiles) {
                User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
                $receipt = DB::table('sync_receipts')->where('user_id', $r->user()->id)->where('operation_id', $d['operation_id'])->first();
                if ($receipt) {
                    abort_if($receipt->request_hash !== $hash, 422, 'Operation ID was reused with different content.');

                    $saved = json_decode($receipt->response, true);
                    if (isset($saved['_access'])) {
                        $access = $saved['_access'];
                        $savedCard = Card::find($access['card_id']);
                        if ($savedCard) {
                            Gate::forUser($r->user())->authorize($method === 'DELETE' && str_starts_with($path, '/cards/') ? 'delete' : 'update', $savedCard);
                        } else {
                            abort_unless($access['owner_id'] === $r->user()->id, 410, 'Card unavailable.');
                        }
                        unset($saved['_access']);
                    }

                    return response()->json($saved);
                }
                $card = null;
                if (preg_match('#^/cards/(\d+)#', $path, $m)) {
                    $card = Card::whereKey($m[1])->lockForUpdate()->first();
                    $model = $card;
                } elseif (preg_match('#^/(benefits|statements)/(\d+)#', $path, $m)) {
                    $class = $m[1] === 'benefits' ? Benefit::class : Statement::class;
                    $model = $class::find($m[2]);
                    $card = $model?->card;
                    if ($card) {
                        Card::whereKey($card->id)->lockForUpdate()->first();
                        $model->refresh();
                    }
                } else {
                    $model = null;
                }
                if ($model) {
                    Gate::forUser($r->user())->authorize($method === 'DELETE' && $model instanceof Card ? 'delete' : 'update', $model);
                    abort_unless(isset($d['revision']), 422, 'A revision is required.');
                    if ((int) $d['revision'] !== (int) $model->revision) {
                        return response()->json(['message' => 'Conflict: this record changed.', 'server' => $model, 'revision' => $model->revision], 409);
                    }
                } elseif ($path !== '/cards' && $path !== '/cards/import') {
                    return response()->json(['message' => 'The record was deleted or access was revoked.'], 410);
                }
                $files = $r->file('file') ? ['file' => $r->file('file')] : [];
                $sub = Request::create('/api'.$path, $method, $payload, [], $files, ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => $r->header('Authorization')]);
                $sub->setUserResolver(fn () => $r->user());
                $response = app(Kernel::class)->handle($sub);
                $rollbackFiles = $sub->attributes->get('rollback_files', []);
                // Kernel swaps the request binding; restore it for outer response/resources.
                app()->instance('request', $r);
                if ($response->getStatusCode() >= 400) {
                    throw new HttpResponseException($response);
                }
                $result = ['status' => $response->getStatusCode(), 'data' => json_decode($response->getContent(), true)];
                DB::table('sync_receipts')->insert(['user_id' => $r->user()->id, 'operation_id' => $d['operation_id'], 'request_hash' => $hash, 'response' => json_encode($result + ($card ? ['_access' => ['card_id' => $card->id, 'owner_id' => $card->user_id]] : ($path === '/cards' && isset($result['data']['id']) ? ['_access' => ['card_id' => $result['data']['id'], 'owner_id' => $r->user()->id]] : []))), 'created_at' => now(), 'updated_at' => now()]);

                return response()->json($result);
            });
        } catch (\Throwable $error) {
            foreach ($rollbackFiles as $filePath) {
                Storage::disk('local')->delete($filePath);
            }
            throw $error;
        }
    }
}
