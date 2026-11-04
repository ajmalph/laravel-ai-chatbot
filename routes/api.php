<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pgs\LaravelAiChatbot\Http\Controllers\ConfigController;
use Pgs\LaravelAiChatbot\Http\Controllers\ConversationController;
use Pgs\LaravelAiChatbot\Http\Controllers\MessageController;
use Pgs\LaravelAiChatbot\Http\Middleware\AuthorizeConversation;
use Pgs\LaravelAiChatbot\Http\Middleware\ChatbotRateLimit;

Route::middleware(ChatbotRateLimit::class)->group(function (): void {
    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('config', ConfigController::class)->name('config');
    Route::middleware(AuthorizeConversation::class)->group(function (): void {
        Route::post('messages', [MessageController::class, 'store'])->name('messages.store');
        Route::get('conversations/{id}/messages', [ConversationController::class, 'messages'])->whereUuid('id')->name('conversations.messages');
        Route::delete('conversations/{id}', [ConversationController::class, 'destroy'])->whereUuid('id')->name('conversations.destroy');
    });
});
