<?php

declare(strict_types=1);

namespace App\Parsers;

use SimpleXMLElement;

// TODO: make DmarcReport type more stricter, eg. accept only 'fail'|'pass' enum type instead of string type

/**
 * @phpstan-type AlignmentMap array{r: 'relaxed', s: 'strict'}
 * @phpstan-type DmarcReport array{
 *     provider: array{
 *         name: string,
 *         email: string,
 *         extra_contact?: string,
 *     },
 *     domain: string,
 *     report_id: int,
 *     report_period: array{
 *         from: string,
 *         to: string,
 *     },
 *     policy_settings: array{
 *         dkim_alignment: string,
 *         spf_alignment: string,
 *         policy: string,
 *         sub_domain_policy: string,
 *         percentage: int,
 *     },
 *     records: list<array{
 *         source_ip: string,
 *         count: int,
 *         disposition: string,
 *         dkim_result: string,
 *         spf_result: string,
 *         auth_results: array{
 *             dkim: list<array{
 *                 domain: string,
 *                 result: string,
 *             }>,
 *             spf: list<array{
 *                 domain: string,
 *                 result: string,
 *             }>,
 *         },
 *     }>
 * }
 */
final readonly class DmarcXmlParser
{
    /** @var AlignmentMap */
    private array $alignmentMap;

    public function __construct()
    {
        $this->alignmentMap = $this->getAlignmentMap();
    }

    /**
     * @return DmarcReport
     */
    public function parse(SimpleXMLElement $xml): array
    {
        $reportMetadata = $xml->{'report_metadata'};
        $policyPublished = $xml->{'policy_published'};

        $report = [
            'provider' => [
                'name' => (string) $reportMetadata->{'org_name'},
                'email' => (string) $reportMetadata->{'email'},
            ],
            'domain' => (string) $policyPublished->{'domain'},
            'report_id' => (int) $reportMetadata->{'report_id'},
            'report_period' => [
                'from' => date('Y-m-d H:i:s', (int) $reportMetadata->{'date_range'}->{'begin'}),
                'to' => date('Y-m-d H:i:s', (int) $reportMetadata->{'date_range'}->{'end'}),
            ],
            'policy_settings' => [
                'dkim_alignment' => $this->alignmentMap[(string) $policyPublished->{'adkim'}],
                'spf_alignment' => $this->alignmentMap[(string) $policyPublished->{'aspf'}],
                'policy' => (string) $policyPublished->{'p'},
                'sub_domain_policy' => (string) $policyPublished->{'sp'},
                'percentage' => (int) $policyPublished->{'pct'},
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
                'count' => (int) $rowData->{'count'},
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

    /**
     * @return AlignmentMap
     */
    private function getAlignmentMap(): array
    {
        return [
            'r' => 'relaxed',
            's' => 'strict',
        ];
    }
}
