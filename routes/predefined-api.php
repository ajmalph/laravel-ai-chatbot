<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Pgs\LaravelAiChatbot\Http\Controllers\PredefinedQuestionApiController;

Route::get('config', [PredefinedQuestionApiController::class, 'config']);
Route::get('categories', [PredefinedQuestionApiController::class, 'categories']);
Route::get('categories/{category}/questions', [PredefinedQuestionApiController::class, 'categoryQuestions'])->whereNumber('category');
Route::get('questions/{question}', [PredefinedQuestionApiController::class, 'question'])->whereNumber('question');
