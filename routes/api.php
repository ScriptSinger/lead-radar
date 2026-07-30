<?php

use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\VkApiHealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->name('telegram.webhook');

Route::get('/vk/health', VkApiHealthController::class)
    ->middleware('throttle:10,1')
    ->name('vk.health');
