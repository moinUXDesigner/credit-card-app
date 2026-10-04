<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\MessageParser;
use App\Services\SpendAggregationService;
use App\Services\StatementImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MessageImportController extends Controller
{
    public function preview(Request $r, Card $card)
    {
        $this->authorize('update', $card);
        $d = $r->validate(['text' => 'nullable|string|max:5242880', 'file' => 'nullable|file|max:5120|extensions:txt,eml']);
        $text = $r->file('file') ? file_get_contents($r->file('file')->getRealPath()) : ($d['text'] ?? '');
        abort_unless(trim($text), 422, 'Paste a message or upload a file.');
        $hash = hash('sha256', $text);
        if (DB::table('message_imports')->where('card_id', $card->id)->where('fingerprint', $hash)->exists()) {
            return response()->json(['duplicate' => true, 'rows' => [], 'summary' => []]);
        }
        $parsed = app(MessageParser::class)->parse($text, $r->file('file')?->getClientOriginalExtension() === 'eml');
        foreach ($parsed['rows'] as &$row) {
            $row['possible_duplicate'] = $row['transaction_date'] && $card->transactions()->whereDate('transaction_date', $row['transaction_date'])->where('amount', $row['amount'])->exists();
        }
        $id = (string) Str::uuid();
        DB::table('message_previews')->where('expires_at', '<', now())->delete();
        DB::table('message_previews')->insert(['id' => $id, 'user_id' => $r->user()->id, 'card_id' => $card->id, 'fingerprint' => $hash, 'summary' => json_encode($parsed['summary']), 'expires_at' => now()->addHour()]);

        return response()->json(['preview_id' => $id, 'duplicate' => false, ...$parsed]);
    }

    public function confirm(Request $r, Card $card)
    {
        $this->authorize('update', $card);
        $d = $r->validate(['preview_id' => 'required|uuid', 'rows' => 'present|array|max:200', 'rows.*.transaction_date' => 'required|date_format:Y-m-d', 'rows.*.description' => 'required|string|max:255', 'rows.*.amount' => 'required|numeric|min:0.01|max:9999999999', 'rows.*.direction' => 'required|in:purchase,credit', 'rows.*.category' => ['required', Rule::in(Card::CATEGORIES)], 'rows.*.duplicate_action' => 'nullable|in:skip,keep', 'apply_summary' => 'required|boolean']);

        return DB::transaction(function () use ($r, $card, $d) {
            Card::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $preview = DB::table('message_previews')->where('id', $d['preview_id'])->where('card_id', $card->id)->where('user_id', $r->user()->id)->lockForUpdate()->first();
            abort_unless($preview && now()->lt($preview->expires_at), 422, 'Preview expired. Parse the message again.');
            if (DB::table('message_imports')->where('card_id', $card->id)->where('fingerprint', $preview->fingerprint)->exists()) {
                return response()->json(['imported' => 0, 'duplicate' => true]);
            }
            $periods = [];
            $count = 0;
            foreach ($d['rows'] as $row) {
                $candidate = $card->transactions()->whereDate('transaction_date', $row['transaction_date'])->where('amount', $row['amount'])->exists();
                abort_if($candidate && ! isset($row['duplicate_action']), 422, 'Review possible duplicates and choose skip or keep.');
                if (($row['duplicate_action'] ?? null) === 'skip') {
                    continue;
                }
                unset($row['duplicate_action']);
                $row['source'] = 'message';
                $row['fingerprint'] = hash('sha256', json_encode([$row['transaction_date'], $row['amount'], mb_strtolower($row['description']), $row['direction']]));
                $card->transactions()->create($row);
                $count++;
                $date = Carbon::parse($row['transaction_date']);
                $periods[$date->format('Y-m')] = ['year' => $date->year, 'month' => $date->month];
            }
            if ($d['apply_summary']) {
                $summary = json_decode($preview->summary, true);
                if ($summary) {
                    app(StatementImportService::class)->markExternal($card, $summary, 'message');
                    $card->fill($summary)->save();
                }
            }
            app(SpendAggregationService::class)->recompute($card, array_values($periods));
            DB::table('message_imports')->insert(['card_id' => $card->id, 'fingerprint' => $preview->fingerprint, 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['imported' => $count, 'duplicate' => false], 201);
        });
    }
}
