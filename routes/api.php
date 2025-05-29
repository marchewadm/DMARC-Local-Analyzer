<?php

declare(strict_types=1);

use App\Http\Controllers\AuthenticatedUserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user/me', [AuthenticatedUserController::class, 'show']);

    Route::delete('/user/me', [AuthenticatedUserController::class, 'destroy'])
        ->middleware('password.confirm');
});
