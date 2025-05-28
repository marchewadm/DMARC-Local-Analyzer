<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Dmarc\InvalidXmlException;
use App\Http\Requests\StoreDmarcReportRequest;
use App\Models\User;
use App\Services\DmarcReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Throwable;

final class DmarcReportController extends Controller
{
    public function __construct(private readonly DmarcReportService $dmarcReportService) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @throws InvalidXmlException
     * @throws Throwable
     */
    public function store(StoreDmarcReportRequest $request): JsonResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('dmarc_reports');
        /** @var User $user */
        $user = $request->user();

        $xmlFiles = $this->dmarcReportService->loadXmlFiles($files);
        $reports = $this->dmarcReportService->parseMultipleReports($xmlFiles);

        $this->dmarcReportService->storeDmarcReport($reports, $user);

        return response()->json($reports);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
