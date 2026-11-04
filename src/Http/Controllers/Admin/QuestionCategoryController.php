<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pgs\LaravelAiChatbot\Admin\AdminAuthorizationService;
use Pgs\LaravelAiChatbot\Http\Requests\Admin\QuestionCategoryRequest;
use Pgs\LaravelAiChatbot\Models\QuestionCategory;

final class QuestionCategoryController
{
    public function __construct(private readonly AdminAuthorizationService $authorization) {}

    public function index(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.view');

        return view('ai-chatbot::admin.predefined.categories.index', ['categories' => QuestionCategory::query()->withCount('questions')->orderBy('sort_order')->paginate(25)]);
    }

    public function create(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');

        return view('ai-chatbot::admin.predefined.categories.form', ['category' => new QuestionCategory]);
    }

    public function store(QuestionCategoryRequest $request): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        QuestionCategory::query()->create($request->validated());

        return redirect()->route('ai-chatbot.admin.question-categories.index')->with('status', 'Category created.');
    }

    public function edit(Request $request, QuestionCategory $category): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');

        return view('ai-chatbot::admin.predefined.categories.form', compact('category'));
    }

    public function update(QuestionCategoryRequest $request, QuestionCategory $category): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        $category->update($request->validated());

        return back()->with('status', 'Category updated.');
    }

    public function destroy(Request $request, QuestionCategory $category): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        $request->validate(['confirm' => ['required', 'accepted']]);
        abort_if($category->questions()->exists(), 409, 'Remove or reassign category questions first.');
        $category->delete();

        return back()->with('status', 'Category deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.questions.manage');
        $data = $request->validate(['positions' => ['required', 'array', 'max:500'], 'positions.*' => ['integer', 'min:0', 'max:100000']]);
        $ids = QuestionCategory::query()->whereKey(array_keys($data['positions']))->pluck('id');
        abort_unless($ids->count() === count($data['positions']), 422);
        DB::transaction(fn () => collect($data['positions'])->each(fn (int|string $position, int|string $id) => QuestionCategory::query()->whereKey((int) $id)->update(['sort_order' => (int) $position])));

        return back()->with('status', 'Category order updated.');
    }
}
