<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AnswerCardChat;
use App\Models\AiRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Statement;
use App\Models\StatementPreview;
use App\Services\ChatAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CardChatController extends Controller
{
    public function index(Request $request)
    {
        $access = app(ChatAccessService::class);

        return response()->json(ChatConversation::where('user_id', $request->user()->id)->latest('updated_at')->paginate(30)->through(function ($conversation) use ($request, $access) {
            $restricted = false;
            try {
                $access->assert($request->user(), $conversation);
            } catch (\Throwable) {
                $restricted = true;
            }

            return ['id' => $conversation->id, 'title' => $restricted ? 'Restricted conversation' : $conversation->title, 'updated_at' => $conversation->updated_at, 'restricted' => $restricted];
        }));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['card_id' => 'nullable|integer', 'statement_id' => 'nullable|integer']);
        $access = app(ChatAccessService::class);
        if (! empty($data['card_id'])) {
            abort_unless($access->cards($request->user())->whereKey($data['card_id'])->exists(), 403);
        }
        if (! empty($data['statement_id'])) {
            $statement = Statement::findOrFail($data['statement_id']);
            abort_unless($access->cards($request->user())->whereKey($statement->card_id)->exists(), 403);
            abort_if(! empty($data['card_id']) && $statement->card_id !== $data['card_id'], 422, 'The statement belongs to a different card.');
        }
        $ids = array_values(array_filter([$data['card_id'] ?? null, isset($statement) ? $statement->card_id : null]));

        return response()->json(ChatConversation::create(['id' => (string) Str::uuid(), 'user_id' => $request->user()->id, ...$data, 'referenced_card_ids' => $ids]), 201);
    }

    public function show(Request $request, ChatConversation $conversation)
    {
        app(ChatAccessService::class)->assert($request->user(), $conversation);

        $messages = $conversation->messages()->get();
        $requests = AiRequest::whereIn('message_id', $messages->pluck('id'))->pluck('id', 'message_id');

        return response()->json([...$conversation->toArray(), 'messages' => $messages->map(fn ($message) => [...$message->toArray(), 'request_id' => $requests[$message->id] ?? null])]);
    }

    public function destroy(Request $request, ChatConversation $conversation)
    {
        app(ChatAccessService::class)->assert($request->user(), $conversation, true);
        $conversation->delete();

        return response()->json(null, 204);
    }

    public function message(Request $request, ChatConversation $conversation)
    {
        app(ChatAccessService::class)->assert($request->user(), $conversation);
        $data = $request->validate(['client_id' => 'required|uuid', 'content' => 'required|string|max:4000']);
        abort_unless(config('services.openai.api_key'), 503, 'Card Chat is not configured yet. Your draft has been kept.');

        return DB::transaction(function () use ($request, $conversation, $data) {
            $conversation = ChatConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $existing = $conversation->messages()->where('client_id', $data['client_id'])->where('role', 'assistant')->first();
            if ($existing) {
                $original = $conversation->messages()->where('client_id', $data['client_id'])->where('role', 'user')->first();
                abort_if($original?->content !== $data['content'], 422, 'Message key was reused with different text.');
                $job = AiRequest::where('message_id', $existing->id)->first();
                if ($job?->status === 'failed') {
                    abort_if($conversation->messages()->where('status', 'processing')->exists(), 409, 'Another answer is still processing.');
                    $existing->update(['status' => 'processing', 'content' => null]);
                    $job->update(['status' => 'processing', 'error' => null, 'started_at' => null, 'finished_at' => null]);
                    AnswerCardChat::dispatch($job->id)->afterCommit();
                }

                return response()->json(['request_id' => $job?->id, 'message_id' => $existing->id, 'status' => $job?->status], 202);
            }
            abort_if($conversation->messages()->where('status', 'processing')->exists(), 409, 'An answer is still processing in this conversation.');
            $position = (int) $conversation->messages()->max('position');
            $conversation->messages()->create(['position' => $position + 1, 'id' => (string) Str::uuid(), 'client_id' => $data['client_id'], 'role' => 'user', 'status' => 'ready', 'content' => $data['content']]);
            $answer = $conversation->messages()->create(['position' => $position + 2, 'id' => (string) Str::uuid(), 'client_id' => $data['client_id'], 'role' => 'assistant']);
            $job = AiRequest::create(['id' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'kind' => 'chat', 'message_id' => $answer->id, 'model' => config('services.openai.chat_model', config('services.openai.model'))]);
            if ($conversation->title === 'New conversation') {
                $conversation->title = Str::limit(preg_replace('/\s+/', ' ', trim($data['content'])), 80);
            }
            $conversation->save();
            $conversation->touch();
            AnswerCardChat::dispatch($job->id)->afterCommit();

            return response()->json(['request_id' => $job->id, 'message_id' => $answer->id, 'status' => 'processing'], 202);
        });
    }

    public function requestStatus(Request $request, AiRequest $aiRequest)
    {
        abort_unless($aiRequest->user_id === $request->user()->id, 404);
        if ($aiRequest->message_id) {
            $message = ChatMessage::findOrFail($aiRequest->message_id);
            $conversation = ChatConversation::findOrFail($message->conversation_id);
            app(ChatAccessService::class)->assert($request->user(), $conversation);
        }
        if ($aiRequest->preview_id) {
            $preview = StatementPreview::findOrFail($aiRequest->preview_id);
            if ($preview->card_id) {
                abort_unless(app(ChatAccessService::class)->cards($request->user())->whereKey($preview->card_id)->exists(), 403);
            }
        }

        return response()->json(['id' => $aiRequest->id, 'status' => $aiRequest->status, 'error' => $aiRequest->error]);
    }
}
