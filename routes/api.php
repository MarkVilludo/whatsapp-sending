<?php

use App\Http\Controllers\InfobipSmsController;
use Illuminate\Support\Facades\Route;


Route::get('/test', function () {
    return response()->json([
        'message' => 'Hello World',
    ]);
});

Route::prefix('infobip/sms')->group(function (): void {
    Route::post('/send', [InfobipSmsController::class, 'send']);
    Route::post('/webhook', [InfobipSmsController::class, 'receive']);
});
