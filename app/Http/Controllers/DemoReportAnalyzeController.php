<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Dmarc\InvalidXmlException;
use App\Exceptions\Dmarc\MissingFieldsException;
use App\Http\Requests\DemoReportAnalyzeRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final class DemoReportAnalyzeController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    /**
     * Analyze a DMARC XML report uploaded by the user.
     *
     * @param  DemoReportAnalyzeRequest  $request  Validated request containing uploaded DMARC XML file.
     * @return JsonResponse A structured JSON representation of parsed DMARC report.
     *
     * @throws InvalidXmlException If the XML is invalid.
     * @throws MissingFieldsException If required DMARC fields are missing.
     */
    public function __invoke(DemoReportAnalyzeRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('dmarc_report');
        $report = $this->reportService->parseSingleReport($file);

        return response()->json($report);
    }
}
