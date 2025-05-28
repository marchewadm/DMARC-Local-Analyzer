<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Dmarc\InvalidXmlException;
use App\Exceptions\Dmarc\MissingFieldsException;
use App\Parsers\DmarcXmlParser;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;

/**
 * @phpstan-import-type DmarcReport from DmarcXmlParser
 */
final readonly class DmarcReportService
{
    public function __construct(private DmarcXmlParser $dmarcXmlParser) {}

    /**
     * Parse one or more DMARC XML report files.
     *
     * @param  UploadedFile[]  $files  Uploaded XML files to be parsed.
     * @return DmarcReport[] Structured array representation of the DMARC reports.
     *
     * @throws InvalidXmlException If the XML is invalid.
     * @throws MissingFieldsException If required DMARC fields are missing.
     */
    public function parseMultipleReports(array $files): array
    {
        $reports = [];

        foreach ($files as $file) {
            $reports[] = $this->parseSingleReport($file);
        }

        return $reports;
    }

    /**
     * Parse a single uploaded DMARC XML report file.
     *
     * @param  UploadedFile  $file  The uploaded XML file to be parsed.
     * @return DmarcReport Structured array representation of the DMARC report.
     *
     * @throws InvalidXmlException If the XML is invalid.
     * @throws MissingFieldsException If required DMARC fields are missing.
     */
    public function parseSingleReport(UploadedFile $file): array
    {
        $filePathname = $file->getPathname();
        $xml = simplexml_load_file($filePathname);

        if (! $xml instanceof SimpleXMLElement) {
            throw new InvalidXmlException;
        }

        if (! isset($xml->{'report_metadata'}, $xml->{'policy_published'})) {
            throw new MissingFieldsException;
        }

        return $this->dmarcXmlParser->parse($xml);
    }
}
