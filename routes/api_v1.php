<?php

declare(strict_types=1);

use App\Http\Controllers\DemoReportAnalyzeController;
use Illuminate\Support\Facades\Route;

Route::post('/demo/report/analyze', DemoReportAnalyzeController::class);
