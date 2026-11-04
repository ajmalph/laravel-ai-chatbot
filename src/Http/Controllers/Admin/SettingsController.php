<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pgs\LaravelAiChatbot\Admin\AdminAuthorizationService;
use Pgs\LaravelAiChatbot\Admin\SettingsService;
use Pgs\LaravelAiChatbot\Http\Requests\Admin\SettingsRequest;

final class SettingsController
{
    public function __construct(private readonly AdminAuthorizationService $authorization, private readonly SettingsService $settings) {}

    public function edit(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.settings');

        return view('ai-chatbot::admin.settings', ['settings' => $this->settings->all()]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.settings');
        $this->settings->update($request->validated());

        return back()->with('status', 'Settings updated.');
    }
}
