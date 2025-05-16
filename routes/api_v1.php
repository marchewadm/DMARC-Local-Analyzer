<?php

declare(strict_types=1);

use App\Http\Controllers\DmarcReportAnalyzeController;
use Illuminate\Support\Facades\Route;

Route::post('/demo/report/analyze', DmarcReportAnalyzeController::class);
