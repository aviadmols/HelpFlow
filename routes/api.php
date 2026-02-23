<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('chat')->group(function () {
    Route::post('start', [ChatController::class, 'start']);
    Route::post('{conversation}/message', [ChatController::class, 'message']);
    Route::post('{conversation}/option', [ChatController::class, 'option']);
    Route::get('{conversation}/stream', [ChatController::class, 'stream']);
});
