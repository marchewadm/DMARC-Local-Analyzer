<?php

declare(strict_types=1);

use App\Http\Controllers\DmarcReportAnalyzeController;
use App\Http\Controllers\DmarcReportController;
use Illuminate\Support\Facades\Route;

Route::post('/demo/report/analyze', DmarcReportAnalyzeController::class);

Route::apiResource('report', DmarcReportController::class)
    ->middleware('auth:sanctum');
