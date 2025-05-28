<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Dmarc\InvalidXmlException;
use App\Models\User;
use App\Parsers\DmarcXmlParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use Throwable;

/**
 * @phpstan-import-type DmarcReport from DmarcXmlParser
 */
final readonly class DmarcReportService
{
    public function __construct(private DmarcXmlParser $dmarcXmlParser) {}

    /**
     * Parse one or more DMARC XML report files.
     *
     * @param  list<SimpleXMLElement>  $xmlFiles  XML files to be parsed.
     * @return list<DmarcReport> List of structured arrays representing multiple DMARC reports.
     */
    public function parseMultipleReports(array $xmlFiles): array
    {
        $reports = [];

        foreach ($xmlFiles as $file) {
            $reports[] = $this->parseSingleReport($file);
        }

        return $reports;
    }

    /**
     * Parse a single DMARC XML report file.
     *
     * @param  SimpleXMLElement  $xml  XML file to be parsed.
     * @return DmarcReport Structured array representation of a single DMARC report.
     */
    public function parseSingleReport(SimpleXMLElement $xml): array
    {
        return $this->dmarcXmlParser->parse($xml);
    }

    /**
     * Store one or more DMARC reports and their associated records and results for a given user.
     *
     * Each DMARC report includes policy information, reporting period, and a list of records
     * with associated DKIM and SPF authentication results.
     *
     * @param  list<DmarcReport>  $dmarcReports  Array of parsed DMARC reports.
     * @param  User  $user  The user to associate the reports with.
     *
     * @throws Throwable If the transaction fails.
     */
    public function storeDmarcReport(array $dmarcReports, User $user): void
    {

        DB::transaction(function () use ($dmarcReports, $user) {
            foreach ($dmarcReports as $dmarcReport) {
                $storedDmarcReport = $user->dmarcReports()->create([
                    'provider_name' => $dmarcReport['provider']['name'],
                    'provider_email' => $dmarcReport['provider']['email'],
                    'provider_extra_contact' => $dmarcReport['provider']['extra_contact'] ?? null,
                    'report_start' => $dmarcReport['report_period']['from'],
                    'report_end' => $dmarcReport['report_period']['to'],
                    'dkim_alignment' => $dmarcReport['policy_settings']['dkim_alignment'],
                    'spf_alignment' => $dmarcReport['policy_settings']['spf_alignment'],
                    'policy' => $dmarcReport['policy_settings']['policy'],
                    'sub_domain_policy' => $dmarcReport['policy_settings']['sub_domain_policy'],
                    'percentage' => $dmarcReport['policy_settings']['percentage'],
                    'domain' => $dmarcReport['domain'],
                    'report_id' => $dmarcReport['report_id'],
                ]);

                foreach ($dmarcReport['records'] as $dmarcRecord) {
                    $record = $storedDmarcReport->dmarcRecords()->create([
                        'source_ip' => $dmarcRecord['source_ip'],
                        'count' => $dmarcRecord['count'],
                        'disposition' => $dmarcRecord['disposition'],
                        'dkim_result' => $dmarcRecord['dkim_result'],
                        'spf_result' => $dmarcRecord['spf_result'],
                    ]);

                    $record->dmarcDkimResults()->createMany($dmarcRecord['auth_results']['dkim']);
                    $record->dmarcSpfResults()->createMany($dmarcRecord['auth_results']['spf']);
                }
            }
        });
    }

    /**
     * Loads a list of uploaded XML files as SimpleXMLElement objects.
     *
     * @param  list<UploadedFile>  $files  An array of uploaded XML files.
     * @return list<SimpleXMLElement> An array of loaded XML documents.
     *
     * @throws InvalidXmlException If any of the files contain invalid XML.
     */
    public function loadXmlFiles(array $files): array
    {
        /** @var list<SimpleXMLElement> $xmlFiles */
        $xmlFiles = [];

        foreach ($files as $file) {
            $xmlFiles[] = $this->loadXmlFile($file);
        }

        return $xmlFiles;
    }

    /**
     * Loads an uploaded XML file and returns it as a SimpleXMLElement.
     *
     * @param  UploadedFile  $file  The uploaded XML file.
     * @return SimpleXMLElement The loaded XML element.
     *
     * @throws InvalidXmlException If the XML is invalid.
     */
    public function loadXmlFile(UploadedFile $file): SimpleXMLElement
    {
        $filePathname = $file->getPathname();
        $xmlFile = simplexml_load_file($filePathname);

        if (! $xmlFile instanceof SimpleXMLElement) {
            throw new InvalidXmlException;
        }

        return $xmlFile;
    }

    /**
     * Deletes the specified DMARC reports associated with the given user.
     *
     * @param  list<int>  $ids  An array of DMARC report IDs to delete.
     * @param  User  $user  The user who owns the reports.
     *
     * @throws Throwable If the transaction fails.
     */
    public function deleteDmarcReport(array $ids, User $user): void
    {
        DB::transaction(function () use ($ids, $user) {
            $user->dmarcReports()->whereIn('id', $ids)->delete();
        });
    }
}
