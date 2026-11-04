<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Pgs\LaravelAiChatbot\Admin\AdminAuthorizationService;
use Pgs\LaravelAiChatbot\Models\Conversation;

final class ConversationController
{
    public function __construct(private readonly AdminAuthorizationService $authorization) {}

    public function index(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.view');
        $query = Conversation::query()->withCount('messages')->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        return view('ai-chatbot::admin.conversations.index', ['conversations' => $query->paginate(25)->withQueryString()]);
    }

    public function show(Request $request, string $id): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.view');
        $conversation = Conversation::withTrashed()->findOrFail($id);
        $messages = $conversation->messages()->select(['id', 'role', 'content', 'status', 'input_tokens', 'output_tokens', 'latency_ms', 'error_code', 'created_at'])->latest()->limit(100)->get()->reverse();

        return view('ai-chatbot::admin.conversations.show', compact('conversation', 'messages'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.delete');
        $conversation = Conversation::query()->findOrFail($id);
        $retention = max(0, (int) config('ai-chatbot.admin.retention_days', 30));
        if ($retention > 0 && $conversation->created_at->isAfter(now()->subDays($retention))) {
            throw ValidationException::withMessages(['conversation' => 'The conversation is still within the retention period.']);
        }
        $conversation->update(['status' => 'deleted']);
        $conversation->delete();

        return back()->with('status', 'Conversation deleted.');
    }
}
