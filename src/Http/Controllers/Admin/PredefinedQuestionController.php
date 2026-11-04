<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pgs\LaravelAiChatbot\Admin\AdminAuthorizationService;
use Pgs\LaravelAiChatbot\Http\Requests\Admin\PredefinedQuestionRequest;
use Pgs\LaravelAiChatbot\Models\PredefinedQuestion;
use Pgs\LaravelAiChatbot\Models\QuestionCategory;

final class PredefinedQuestionController
{
    public function __construct(private readonly AdminAuthorizationService $authorization) {}

    public function index(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.view');
        $questions = PredefinedQuestion::query()->with('category:id,title_en')->when($request->integer('category'), fn (Builder $q, int $id) => $q->where('category_id', $id))->when($request->filled('search'), fn (Builder $q) => $q->where(fn (Builder $nested) => $nested->where('question_en', 'like', '%'.$request->string('search').'%')->orWhere('question_ar', 'like', '%'.$request->string('search').'%')))->orderBy('category_id')->orderBy('sort_order')->paginate(25)->withQueryString();

        return view('ai-chatbot::admin.predefined.questions.index', ['questions' => $questions, 'categories' => QuestionCategory::query()->orderBy('sort_order')->get(['id', 'title_en'])]);
    }

    public function create(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');

        return $this->form(new PredefinedQuestion);
    }

    public function store(PredefinedQuestionRequest $request): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        DB::transaction(function () use ($request): void {
            $data = $request->safe()->except('followup_ids');
            $question = PredefinedQuestion::query()->create($data);
            $this->syncFollowups($question, $request->validated('followup_ids', []));
        });

        return redirect()->route('ai-chatbot.admin.predefined-questions.index')->with('status', 'Question created.');
    }

    public function edit(Request $request, PredefinedQuestion $question): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        $question->load('followups:id');

        return $this->form($question);
    }

    public function update(PredefinedQuestionRequest $request, PredefinedQuestion $question): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        DB::transaction(function () use ($request, $question): void {
            $question->update($request->safe()->except('followup_ids'));
            $this->syncFollowups($question, $request->validated('followup_ids', []));
        });

        return back()->with('status', 'Question updated.');
    }

    public function destroy(Request $request, PredefinedQuestion $question): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        $request->validate(['confirm' => ['required', 'accepted']]);
        $question->delete();

        return back()->with('status', 'Question deleted.');
    }

    private function form(PredefinedQuestion $question): View
    {
        return view('ai-chatbot::admin.predefined.questions.form', ['question' => $question, 'categories' => QuestionCategory::query()->orderBy('sort_order')->get(), 'availableQuestions' => PredefinedQuestion::query()->when($question->exists, fn (Builder $query) => $query->where('id', '!=', $question->getKey()))->orderBy('question_en')->get(['id', 'question_en'])]);
    }

    private function syncFollowups(PredefinedQuestion $question, array $ids): void
    {
        $question->followups()->sync(collect($ids)->values()->mapWithKeys(fn (int|string $id, int $order) => [(int) $id => ['sort_order' => $order]])->all());
    }
}
