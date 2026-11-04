<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pgs\LaravelAiChatbot\Admin\AdminAuthorizationService;
use Pgs\LaravelAiChatbot\Http\Requests\Admin\KnowledgeSyncRequest;
use Pgs\LaravelAiChatbot\Knowledge\KnowledgeSourceRegistry;
use Pgs\LaravelAiChatbot\Models\KnowledgeChunk;
use Pgs\LaravelAiChatbot\Services\KnowledgeIndexService;

final class KnowledgeController
{
    public function __construct(private readonly AdminAuthorizationService $authorization, private readonly KnowledgeSourceRegistry $sources, private readonly KnowledgeIndexService $indexer) {}

    public function index(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.view');
        $query = KnowledgeChunk::query()->select(['source_type', 'source_id', 'locale', 'title', 'url', 'source_modified_at'])
            ->selectRaw('COUNT(*) as chunks')->groupBy('source_type', 'source_id', 'locale', 'title', 'url', 'source_modified_at')
            ->latest('source_modified_at');
        if ($request->filled('source')) {
            $query->where('source_type', $request->string('source')->toString());
        }

        return view('ai-chatbot::admin.knowledge', ['records' => $query->paginate(25)->withQueryString(), 'sources' => $this->sources->names()]);
    }

    public function sync(KnowledgeSyncRequest $request): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.sync');
        $data = $request->validated();
        $summary = $this->indexer->sync($data['source'], $data['locale'] ?? null);

        return back()->with('status', "Synchronized {$summary['documents']} records and {$summary['chunks']} chunks.");
    }
}
