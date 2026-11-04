<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Pgs\LaravelAiChatbot\Admin\AdminAuthorizationService;
use Pgs\LaravelAiChatbot\Admin\DashboardService;

final class DashboardController
{
    public function __construct(private readonly AdminAuthorizationService $authorization, private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): View
    {
        $this->authorization->authorize($request->user(), 'ai-chatbot.admin.view');

        return view('ai-chatbot::admin.dashboard', ['metrics' => $this->dashboard->metrics()]);
    }
}
