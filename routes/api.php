<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::post('/telegram/webhook', function (Request $request) {

    $text = strtolower(
        $request->input('message.text')
    );

    $chatId = $request->input('message.chat.id');

    if ($text === 'ping') {

        Http::post(
            'https://api.telegram.org/bot'
                . env('TELEGRAM_BOT_TOKEN')
                . '/sendMessage',
            [
                'chat_id' => $chatId,
                'text' => 'pong'
            ]
        );
    }

    return response()->json([
        'ok' => true
    ]);
});
