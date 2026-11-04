<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pgs\LaravelAiChatbot\Http\Controllers\Admin\ConversationController;
use Pgs\LaravelAiChatbot\Http\Controllers\Admin\DashboardController;
use Pgs\LaravelAiChatbot\Http\Controllers\Admin\KnowledgeController;
use Pgs\LaravelAiChatbot\Http\Controllers\Admin\SettingsController;
use Pgs\LaravelAiChatbot\Http\Controllers\Admin\QuestionCategoryController;
use Pgs\LaravelAiChatbot\Http\Controllers\Admin\PredefinedQuestionController;

Route::get('/', DashboardController::class)->name('dashboard');
Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
Route::get('conversations/{id}', [ConversationController::class, 'show'])->whereUuid('id')->name('conversations.show');
Route::delete('conversations/{id}', [ConversationController::class, 'destroy'])->whereUuid('id')->name('conversations.destroy');
Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
Route::post('knowledge/sync', [KnowledgeController::class, 'sync'])->name('knowledge.sync');
Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('question-categories/reorder', [QuestionCategoryController::class, 'reorder'])->name('question-categories.reorder');
Route::resource('question-categories', QuestionCategoryController::class)->parameters(['question-categories' => 'category'])->except('show');
Route::resource('predefined-questions', PredefinedQuestionController::class)->parameters(['predefined-questions' => 'question'])->except('show');
