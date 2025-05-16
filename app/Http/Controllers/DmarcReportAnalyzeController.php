<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Dmarc\InvalidXmlException;
use App\Exceptions\Dmarc\MissingFieldsException;
use App\Http\Requests\DmarcReportAnalyzeRequest;
use App\Services\DmarcReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final class DmarcReportAnalyzeController extends Controller
{
    public function __construct(private readonly DmarcReportService $dmarcReportService) {}

    /**
     * Analyze a DMARC XML report uploaded by the user.
     *
     * @param  DmarcReportAnalyzeRequest  $request  Validated request containing uploaded DMARC XML file.
     * @return JsonResponse A structured JSON representation of parsed DMARC report.
     *
     * @throws InvalidXmlException If the XML is invalid.
     * @throws MissingFieldsException If required DMARC fields are missing.
     */
    public function __invoke(DmarcReportAnalyzeRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('dmarc_report');
        $report = $this->dmarcReportService->parseSingleReport($file);

        return response()->json($report);
    }
}
