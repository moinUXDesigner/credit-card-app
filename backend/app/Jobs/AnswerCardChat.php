<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\CardChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AnswerCardChat implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public string $requestId)
    {
        $this->onConnection('database')->onQueue('ai');
    }

    public function handle(): void
    {
        $request = AiRequest::find($this->requestId);
        if (! $request || $request->status !== 'processing') {
            return;
        }
        $message = ChatMessage::find($request->message_id);
        $conversation = $message ? ChatConversation::find($message->conversation_id) : null;
        $user = User::find($request->user_id);
        if (! $conversation || ! $user || $user->suspended_at) {
            $this->failed(null);

            return;
        }
        $started = microtime(true);
        if (AiRequest::whereKey($request->id)->where('status', 'processing')->whereNull('started_at')->update(['started_at' => now()]) !== 1) {
            return;
        }
        try {
            $result = app(CardChatService::class)->answer($user, $conversation, $message);
            if (! $message->fresh() || ! $request->fresh()) {
                return;
            }
            $message->update(['status' => 'ready', 'content' => $result['content'], 'sources' => $result['sources']]);
            $request->update(['status' => 'ready', 'usage' => $result['usage'], 'finished_at' => now(), 'duration_ms' => (int) ((microtime(true) - $started) * 1000)]);
            Log::info('AI chat finished', ['request_id' => $request->id, 'duration_ms' => $request->duration_ms, 'usage' => $request->usage]);
        } catch (\Throwable) {
            $this->failed(null);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $request = AiRequest::find($this->requestId);
        if (! $request || $request->status !== 'processing') {
            return;
        }
        $error = 'Could not answer this question. Retry when AI is available.';
        ChatMessage::whereKey($request->message_id)->update(['status' => 'failed', 'content' => $error]);
        $request->update(['status' => 'failed', 'error' => $error, 'finished_at' => now()]);
        Log::warning('AI chat failed', ['request_id' => $request->id]);
    }
}
