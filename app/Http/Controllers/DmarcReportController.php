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
     * Handles the upload and storage of new DMARC report files.
     *
     * This method retrieves uploaded XML files from the request, loads them,
     * parses the DMARC reports, and stores them in the database under the authenticated user.
     *
     * @param  StoreDmarcReportRequest  $request  The incoming HTTP request containing DMARC report files.
     * @return JsonResponse A JSON response containing the parsed DMARC reports.
     *
     * @throws InvalidXmlException If any of the files contain invalid XML.
     * @throws Throwable If an error occurs during the database transaction.
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
     * Deletes one or more DMARC reports by their IDs.
     *
     * This method accepts a comma-separated list of IDs, converts them to integers,
     * and removes the corresponding DMARC reports belonging to the authenticated user.
     *
     * @param  string  $id  A comma-separated string of DMARC report IDs to delete.
     * @return JsonResponse A JSON response confirming successful deletion.
     *
     * @throws Throwable If an error occurs during the database transaction.
     */
    public function destroy(string $id): JsonResponse
    {
        $rawIds = explode(',', $id);

        /** @var list<int> $reportIds */
        $reportIds = array_map(fn ($id) => (int) $id, $rawIds);
        /** @var User $user */
        $user = request()->user();

        $this->dmarcReportService->deleteDmarcReport($reportIds, $user);

        return response()->json([
            'message' => 'Selected DMARC reports have been successfully deleted.',
        ]);
    }
}
