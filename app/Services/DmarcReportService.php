<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Dmarc\InvalidXmlException;
use App\Exceptions\Dmarc\MissingFieldsException;
use Illuminate\Http\UploadedFile;
use SimpleXMLElement;

/**
 * @phpstan-type DmarcReport array{
 *     provider: array{name: string, email: string, extra_contact?: string},
 *     domain: string,
 *     report_id: string,
 *     report_period: array{from: string, to: string},
 *     policy_settings: array{
 *         dkim_alignment: string,
 *         spf_alignment: string,
 *         policy: string,
 *         sub_domain_policy: string,
 *         percentage: string
 *     },
 *     records: list<array{
 *         source_ip: string,
 *         count: string,
 *         disposition: string,
 *         dkim_result: string,
 *         spf_result: string,
 *         auth_results: array{
 *             dkim: list<array{domain: string, result: string}>,
 *             spf: list<array{domain: string, result: string}>
 *         }
 *     }>
 * }
 */
final class DmarcReportService
{
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

        return $this->transformXmlToArray($xml);
    }

    /**
     * @param  SimpleXMLElement  $xml  Parsed DMARC XML root element.
     * @return DmarcReport Structured array representation of the DMARC report.
     */
    private function transformXmlToArray(SimpleXMLElement $xml): array
    {
        $reportMetadata = $xml->{'report_metadata'};
        $publishedPolicy = $xml->{'policy_published'};

        // === REPORT DATA ===
        $report = [
            'provider' => [
                'name' => (string) $reportMetadata->{'org_name'},
                'email' => (string) $reportMetadata->{'email'},
            ],
            'domain' => (string) $publishedPolicy->{'domain'},
            'report_id' => (string) $reportMetadata->{'report_id'},
            'report_period' => [
                'from' => (string) $reportMetadata->{'date_range'}->{'begin'},
                'to' => (string) $reportMetadata->{'date_range'}->{'end'},
            ],
            'policy_settings' => [
                'dkim_alignment' => (string) $publishedPolicy->{'adkim'},
                'spf_alignment' => (string) $publishedPolicy->{'aspf'},
                'policy' => (string) $publishedPolicy->{'p'},
                'sub_domain_policy' => (string) $publishedPolicy->{'sp'},
                'percentage' => (string) $publishedPolicy->{'pct'},
            ],
            'records' => [],
        ];

        if (isset($reportMetadata->{'extra_contact_info'})) {
            $report['provider']['extra_contact'] = (string) $reportMetadata->{'extra_contact_info'};
        }

        foreach ($xml->{'record'} as $record) {
            $rowData = $record->{'row'};
            $authResults = $record->{'auth_results'};

            $dkimResults = [];
            if (isset($authResults->{'dkim'})) {
                foreach ($authResults->{'dkim'} as $dkim) {
                    $dkimResults[] = [
                        'domain' => (string) $dkim->{'domain'},
                        'result' => (string) $dkim->{'result'},
                    ];
                }
            }

            $spfResults = [];
            if (isset($authResults->{'spf'})) {
                foreach ($authResults->{'spf'} as $spf) {
                    $spfResults[] = [
                        'domain' => (string) $spf->{'domain'},
                        'result' => (string) $spf->{'result'},
                    ];
                }
            }

            $report['records'][] = [
                'source_ip' => (string) $rowData->{'source_ip'},
                'count' => (string) $rowData->{'count'},
                'disposition' => (string) $rowData->{'policy_evaluated'}->{'disposition'},
                'dkim_result' => (string) $rowData->{'policy_evaluated'}->{'dkim'},
                'spf_result' => (string) $rowData->{'policy_evaluated'}->{'spf'},
                'auth_results' => [
                    'dkim' => $dkimResults,
                    'spf' => $spfResults,
                ],
            ];
        }

        return $report;
    }
}
