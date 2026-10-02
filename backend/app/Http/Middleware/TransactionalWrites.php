<?php

namespace App\Http\Middleware;

use App\Models\Benefit;
use App\Models\Card;
use App\Models\Statement;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TransactionalWrites
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS']) || str_starts_with($request->path(), 'api/sync/')) {
            return $next($request);
        }

        try {
            return DB::transaction(function () use ($request, $next) {
                $card = $request->route('card');
                $benefit = $request->route('benefit');
                $statement = $request->route('statement');
                $model = $benefit instanceof Benefit ? $benefit : ($statement instanceof Statement ? $statement : ($card instanceof Card ? $card : null));
                if ($model) {
                    $ability = $model instanceof Card && ($request->method() === 'DELETE' || str_contains($request->path(), '/sharing')) ? 'delete' : 'update';
                    Gate::forUser($request->user())->authorize($ability, $model);
                    $card = $model instanceof Card ? $model : $model->card;
                    Card::whereKey($card->id)->lockForUpdate()->firstOrFail();
                    $model->refresh();
                    if ($request->has('revision') && (int) $request->input('revision') !== (int) $model->revision) {
                        return response()->json(['message' => 'The record changed.', 'server' => $model, 'revision' => $model->revision], 409);
                    }
                }
                $response = $next($request);
                if ($response->getStatusCode() >= 400) {
                    throw new HttpResponseException($response);
                }

                return $response;
            });
        } catch (\Throwable $error) {
            foreach ($request->attributes->get('rollback_files', []) as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }
    }
}
