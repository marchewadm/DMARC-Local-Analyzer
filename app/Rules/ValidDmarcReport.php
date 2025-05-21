<?php

// TODO: Refactor this validation rule to include additional checks, such as:
// - validating allowed values (e.g., 'adkim' must be 'r' or 's'; 'disposition' must be 'none', 'quarantine', or 'reject')
// - consider setting the 'version' field as a constant with value "1.0"

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use SimpleXMLElement;

/**
 * @phpstan-type ValidatorKeyword 'required'|'trim'|'int'|'float'|'email'|'domain'|'url'|'ip'
 * @phpstan-type SingleFieldRule array{path: string, validators: list<ValidatorKeyword>}
 * @phpstan-type FlatFieldValidationMap array<string, SingleFieldRule>
 * @phpstan-type NestedFieldRule array{path: string, children: list<SingleFieldRule>}
 * @phpstan-type NestedFieldValidationMap array<string, NestedFieldRule>
 */
final class ValidDmarcReport implements ValidationRule
{
    /** @var FlatFieldValidationMap */
    private const FLAT_FIELD_VALIDATION_MAP = [
        'version' => ['path' => '/feedback/version', 'validators' => ['required', 'trim', 'float']],
        'org_name' => ['path' => '/feedback/report_metadata/org_name', 'validators' => ['required', 'trim', 'domain']],
        'email' => ['path' => '/feedback/report_metadata/email', 'validators' => ['required', 'trim', 'email']],
        'extra_contact_info' => ['path' => '/feedback/report_metadata/extra_contact_info', 'validators' => ['trim', 'url']],
        'report_id' => ['path' => '/feedback/report_metadata/report_id', 'validators' => ['required', 'trim', 'int']],
        'begin' => ['path' => '/feedback/report_metadata/date_range/begin', 'validators' => ['required', 'trim', 'int']],
        'end' => ['path' => '/feedback/report_metadata/date_range/end', 'validators' => ['required', 'trim', 'int']],

        'domain' => ['path' => '/feedback/policy_published/domain', 'validators' => ['required', 'trim', 'domain']],
        'adkim' => ['path' => '/feedback/policy_published/adkim', 'validators' => ['required', 'trim']],
        'aspf' => ['path' => '/feedback/policy_published/aspf', 'validators' => ['required', 'trim']],
        'p' => ['path' => '/feedback/policy_published/p', 'validators' => ['required', 'trim']],
        'sp' => ['path' => '/feedback/policy_published/sp', 'validators' => ['required', 'trim']],
        'pct' => ['path' => '/feedback/policy_published/pct', 'validators' => ['required', 'trim', 'int']],
        'np' => ['path' => '/feedback/policy_published/np', 'validators' => ['required', 'trim']],

        'source_ip' => ['path' => '/feedback/record/row/source_ip', 'validators' => ['required', 'trim', 'ip']],
        'count' => ['path' => '/feedback/record/row/count', 'validators' => ['required', 'trim', 'int']],
        'disposition' => ['path' => '/feedback/record/row/policy_evaluated/disposition', 'validators' => ['required', 'trim']],
        'dkim' => ['path' => '/feedback/record/row/policy_evaluated/dkim', 'validators' => ['required', 'trim']],
        'spf' => ['path' => '/feedback/record/row/policy_evaluated/spf', 'validators' => ['required', 'trim']],

        'header_from' => ['path' => '/feedback/record/identifiers/header_from', 'validators' => ['required', 'trim', 'domain']],
    ];

    /** @var NestedFieldValidationMap */
    private const NESTED_FIELD_VALIDATION_MAP = [
        'dkim' => [
            'path' => '/feedback/record/auth_results/dkim',
            'children' => [
                ['path' => 'domain', 'validators' => ['required', 'trim', 'domain']],
                ['path' => 'result', 'validators' => ['required', 'trim']],
                ['path' => 'selector', 'validators' => ['trim']],
            ],
        ],
        'spf' => [
            'path' => '/feedback/record/auth_results/spf',
            'children' => [
                ['path' => 'domain', 'validators' => ['required', 'trim', 'domain']],
                ['path' => 'result', 'validators' => ['required', 'trim']],
            ],
        ],
    ];

    /**
     * @var array<string, callable>
     */
    private readonly array $fieldValidatorMap;

    public function __construct()
    {
        $this->fieldValidatorMap = $this->getFieldValidatorMap();
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        $xml = $this->loadFile($attribute, $value, $fail);

        if ($xml) {
            $this->validateXmlDocument($xml, $fail);
        }

    }

    /**
     * @return array<string, Closure(array<SimpleXMLElement>): bool>
     */
    private function getFieldValidatorMap(): array
    {
        return [
            'required' => fn (array $nodes): bool => ! empty($nodes),

            'trim' => fn (array $nodes): bool => array_reduce($nodes, fn (bool $carry, $node): bool => $carry && ($node instanceof SimpleXMLElement && (string) $node === trim((string) $node)), true
            ),

            'int' => fn (array $nodes): bool => array_reduce($nodes, fn (bool $carry, $node): bool => $carry && ($node instanceof SimpleXMLElement && preg_match('/^\d+$/', (string) $node)), true
            ),

            'float' => fn (array $nodes): bool => array_reduce($nodes, fn (bool $carry, $node): bool => $carry && ($node instanceof SimpleXMLElement && filter_var((string) $node, FILTER_VALIDATE_FLOAT) !== false), true
            ),

            'email' => fn (array $nodes): bool => array_reduce($nodes, fn (bool $carry, $node): bool => $carry && ($node instanceof SimpleXMLElement && filter_var((string) $node, FILTER_VALIDATE_EMAIL) !== false), true
            ),

            'domain' => fn (array $nodes): bool => array_reduce($nodes, fn (bool $carry, $node): bool => $carry && ($node instanceof SimpleXMLElement && filter_var((string) $node, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false), true
            ),

            'url' => fn (array $nodes): bool => array_reduce($nodes, fn (bool $carry, $node): bool => $carry && ($node instanceof SimpleXMLElement && filter_var((string) $node, FILTER_VALIDATE_URL) !== false), true
            ),

            'ip' => fn (array $nodes): bool => array_reduce($nodes, fn (bool $carry, $node): bool => $carry && ($node instanceof SimpleXMLElement && filter_var((string) $node, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false), true
            ),
        ];
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function loadFile(string $attribute, mixed $value, Closure $fail): false|SimpleXMLElement
    {
        /** @var UploadedFile $file */
        $file = $value;

        try {
            $xml = simplexml_load_file($file->getPathname());
        } catch (Exception $e) {
            $fail('The :attribute is not a valid XML document.');

            return false;
        }

        if ($xml === false) {
            $fail('The :attribute is not a valid XML document.');

            return false;
        }

        return $xml;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    private function validateXmlDocument(SimpleXMLElement $xml, Closure $fail): void
    {
        $flatFieldErrors = $this->validateFlatFields($xml);
        $nestedFieldErrors = $this->validateNestedFields($xml);

        $allErrors = [];

        foreach (array_merge_recursive($flatFieldErrors, $nestedFieldErrors) as $validator => $fields) {
            // @phpstan-ignore-next-line
            $fields = array_unique($fields);

            $message = match ($validator) {
                'required' => 'The following required XML fields are missing or empty: ',
                'trim' => 'The following fields contain disallowed whitespace characters: ',
                'int' => 'The following fields do not contain valid integer values: ',
                'float' => 'The following fields do not contain valid floating-point numbers: ',
                'email' => 'The following fields do not contain valid email addresses: ',
                'domain' => 'The following fields are not valid domain names: ',
                'url' => 'The following fields are not valid URLs: ',
                'ip' => 'The following fields are not valid public IPv4 addresses: ',
                default => "The following fields failed validation for rule '{$validator}': ",
            };

            $allErrors[] = $message . implode(', ', $fields);
        }

        if ($allErrors) {
            $fail(implode("\n", $allErrors));
        }
    }

    /**
     * @return array<string, string[]>
     */
    private function validateFlatFields(SimpleXMLElement $xml): array
    {
        $errors = [];

        foreach (self::FLAT_FIELD_VALIDATION_MAP as $field => $rule) {
            $nodes = $xml->xpath($rule['path']);

            foreach ($rule['validators'] as $validator) {
                if (isset($this->fieldValidatorMap[$validator]) && ! $this->fieldValidatorMap[$validator]($nodes)) {
                    $errors[$validator][] = $field;
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string[]>
     */
    private function validateNestedFields(SimpleXMLElement $xml): array
    {
        $errors = [];

        foreach (self::NESTED_FIELD_VALIDATION_MAP as $groupKey => $group) {
            $parentNodes = $xml->xpath($group['path']);

            foreach ($parentNodes as $index => $parentNode) {
                foreach ($group['children'] as $child) {
                    $childPath = $child['path'];
                    $childNodes = $parentNode->xpath($childPath);

                    foreach ($child['validators'] as $validator) {
                        if (isset($this->fieldValidatorMap[$validator]) && ! $this->fieldValidatorMap[$validator]($childNodes)) {
                            $fieldIdentifier = "{$groupKey}[{$index}].{$childPath}";
                            $errors[$validator][] = $fieldIdentifier;
                        }
                    }
                }
            }
        }

        return $errors;
    }
}

// /**
// * @phpstan-type FieldType 'string'|'email'|'int'|'float'|'domain'|'url'
// * @phpstan-type FieldDefinition array{path: string, type: FieldType, required: bool}
// * @phpstan-type FieldDefinitions array<string, FieldDefinition>
// * @phpstan-type GroupedField array{path: string, nodes: array{name: string, path: string, type: FieldType, required: bool}}
// * @phpstan-type GroupedFields array<string, GroupedField>
// */
// final class ValidDmarcReport implements ValidationRule
// {
//    /**
//     * @var FieldDefinitions
//     */
//    private const FIELD_DEFINITIONS = [
//        'version' => ['path' => '/feedback/version', 'type' => 'float', 'required' => true],
//        'org_name' => ['path' => '/feedback/report_metadata/org_name', 'type' => 'domain', 'required' => true],
//        'email' => ['path' => '/feedback/report_metadata/email', 'type' => 'email', 'required' => true],
//        'extra_contact_info' => ['path' => '/feedback/report_metadata/extra_contact_info', 'type' => 'url', 'required' => false],
//        'report_id' => ['path' => '/feedback/report_metadata/report_id', 'type' => 'int', 'required' => true],
//        'begin' => ['path' => '/feedback/report_metadata/date_range/begin', 'type' => 'int', 'required' => true],
//        'end' => ['path' => '/feedback/report_metadata/date_range/end', 'type' => 'int', 'required' => true],
//
//        'domain' => ['path' => '/feedback/policy_published/domain', 'type' => 'domain', 'required' => true],
//        'adkim' => ['path' => '/feedback/policy_published/adkim', 'type' => 'string', 'required' => true],
//        'aspf' => ['path' => '/feedback/policy_published/aspf', 'type' => 'string', 'required' => true],
//        'p' => ['path' => '/feedback/policy_published/p', 'type' => 'string', 'required' => true],
//        'sp' => ['path' => '/feedback/policy_published/sp', 'type' => 'string', 'required' => true],
//        'pct' => ['path' => '/feedback/policy_published/pct', 'type' => 'int', 'required' => true],
//        'np' => ['path' => '/feedback/policy_published/np', 'type' => 'string', 'required' => true],
//
//        'source_ip' => ['path' => '/feedback/record/row/source_ip', 'type' => 'string', 'required' => true],
//        'count' => ['path' => '/feedback/record/row/count', 'type' => 'int', 'required' => true],
//        'disposition' => ['path' => '/feedback/record/row/policy_evaluated/disposition', 'type' => 'string', 'required' => true],
//        'dkim' => ['path' => '/feedback/record/row/policy_evaluated/dkim', 'type' => 'string', 'required' => true],
//        'spf' => ['path' => '/feedback/record/row/policy_evaluated/spf', 'type' => 'string', 'required' => true],
//
//        'header_from' => ['path' => '/feedback/record/identifiers/header_from', 'type' => 'domain', 'required' => true],
//
//        //        'dkim_domain' => ['path' => '/feedback/record/auth_results/dkim/domain', 'type' => 'domain', 'required' => true],
//        //        'dkim_result' => ['path' => '/feedback/record/auth_results/dkim/result', 'type' => 'string', 'required' => true],
//        //        'dkim_selector' => ['path' => '/feedback/record/auth_results/dkim/selector', 'type' => 'string', 'required' => false],
//        //
//        //        'spf_domain' => ['path' => '/feedback/record/auth_results/spf/domain', 'type' => 'domain', 'required' => true],
//        //        'spf_result' => ['path' => '/feedback/record/auth_results/spf/result', 'type' => 'string', 'required' => true],
//    ];
//
//    /**
//     * @var GroupedFields
//     */
//    private const GROUPED_FIELDS = [
//        'dkim' => [
//            'path' => '/feedback/record/auth_results/dkim',
//            'nodes' => [
//                ['name' => 'dkim_domain', 'path' => 'domain', 'type' => 'domain', 'required' => true],
//                ['name' => 'dkim_result', 'path' => 'result', 'type' => 'string', 'required' => true],
//                ['name' => 'dkim_selector', 'path' => 'selector', 'type' => 'string', 'required' => false],
//            ],
//        ],
//        'spf' => [
//            'path' => '/feedback/record/auth_results/spf',
//            'nodes' => [
//                ['name' => 'spf_domain', 'path' => 'domain', 'type' => 'domain', 'required' => true],
//                ['name' => 'spf_result', 'path' => 'result', 'type' => 'string', 'required' => true],
//            ],
//        ],
//    ];
//
//    /**
//     * Run the validation rule.
//     *
//     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
//     */
//    public function validate(string $attribute, mixed $value, Closure $fail): void
//    {
//        /** @var UploadedFile $file */
//        $file = $value;
//
//        try {
//            $xml = simplexml_load_file($file->getPathname());
//        } catch (Exception $e) {
//            $fail('The :attribute is not a valid XML document.');
//
//            return;
//        }
//
//        if ($xml === false) {
//            $fail('The :attribute is not a valid XML document.');
//
//            return;
//        }
//
//        /** @var string[] $validationErrors */
//        $validationErrors = [];
//
//        $missingFields = $this->validateRequiredFields(
//            $xml,
//            self::FIELD_DEFINITIONS
//        );
//        $missingGroupedFields = $this->validateGroupedFields($xml, self::GROUPED_FIELDS);
//
//        if ($missingFields || $missingGroupedFields) {
//            $validationErrors[] = 'The following required XML fields are missing or empty: ' . implode(', ', array_merge($missingFields, $missingGroupedFields)) . '.';
//        }
//
//        $untrimmedFields = $this->validateTrimmedFields(
//            $xml,
//            self::FIELD_DEFINITIONS
//        );
//        if ($untrimmedFields) {
//            $validationErrors[] = 'The following fields contain disallowed whitespace characters: ' . implode(', ', $untrimmedFields) . '.';
//        }
//
//        $invalidNumericFields = $this->validateTypedFields(
//            $xml,
//            self::FIELD_DEFINITIONS,
//            'int',
//            fn (string $value) => preg_match('/^\\d+$/', $value)
//        );
//        if ($invalidNumericFields) {
//            $validationErrors[] = 'The following fields do not contain valid integer values: ' . implode(', ', $invalidNumericFields) . '.';
//        }
//
//        $invalidFloatFields = $this->validateTypedFields(
//            $xml,
//            self::FIELD_DEFINITIONS,
//            'float',
//            fn (string $value) => filter_var($value, FILTER_VALIDATE_FLOAT)
//        );
//        if ($invalidFloatFields) {
//            $validationErrors[] = 'The following fields do not contain valid floating-point numbers: ' . implode(', ', $invalidFloatFields) . '.';
//        }
//
//        $invalidEmailFields = $this->validateTypedFields(
//            $xml,
//            self::FIELD_DEFINITIONS,
//            'email',
//            fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL)
//        );
//        if ($invalidEmailFields) {
//            $validationErrors[] = 'The following fields do not contain valid email addresses: ' . implode(', ', $invalidEmailFields) . '.';
//        }
//
//        $invalidDomainFields = $this->validateTypedFields(
//            $xml,
//            self::FIELD_DEFINITIONS,
//            'domain',
//            fn (string $value) => filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME));
//        if ($invalidDomainFields) {
//            $validationErrors[] = 'The following fields do not contain valid domain addresses: ' . implode(', ', $invalidDomainFields) . '.';
//        }
//
//        $invalidUrlFields = $this->validateTypedFields(
//            $xml,
//            self::FIELD_DEFINITIONS,
//            'url',
//            fn (string $value) => filter_var($value, FILTER_VALIDATE_URL)
//        );
//        if ($invalidUrlFields) {
//            $validationErrors[] = 'The following fields do not contain valid URL addresses: ' . implode(', ', $invalidUrlFields) . '.';
//        }
//
//        if ($validationErrors) {
//            $fail(implode("\n", $validationErrors));
//        }
//    }
//
//    /**
//     * @phpstan-param  FieldDefinitions  $fieldDefinitions
//     *
//     * @return string[]
//     */
//    private function validateRequiredFields(SimpleXMLElement $xml, array $fieldDefinitions): array
//    {
//        $missingFields = [];
//
//        foreach ($fieldDefinitions as $fieldKey => $fieldDefinition) {
//            if (! $fieldDefinition['required']) {
//                continue;
//            }
//
//            $nodes = $xml->xpath($fieldDefinition['path']);
//
//            if (! $nodes) {
//                $missingFields[] = $fieldKey;
//
//                continue;
//            }
//
//            foreach ($nodes as $node) {
//                if (empty(trim((string) $node))) {
//                    $missingFields[] = $fieldKey;
//                    break;
//                }
//            }
//        }
//
//        return $missingFields;
//    }
//
//    /**
//     * @param  GroupedFields  $groupedFields
//     * @return string[]
//     */
//    private function validateGroupedFields(SimpleXMLElement $xml, array $groupedFields): array
//    {
//        $missingFields = [];
//
//        foreach ($groupedFields as $fieldKey => $groupedField) {
//            $nodesArray = $xml->xpath($groupedField['path']);
//
//            foreach ($groupedField['nodes'] as $groupedNode) {
//
//                if (! $groupedNode['required']) {
//                    continue;
//                }
//
//                foreach ($nodesArray as $nodeArray) {
//                    $node = $nodeArray->xpath($groupedNode['path']);
//
//                    if (! $node) {
//                        $missingFields[] = $groupedNode['name'];
//
//                        break;
//                    }
//
//                    foreach ($node as $item) {
//                        if (empty(trim((string) $item))) {
//                            $missingFields[] = $groupedNode['name'];
//
//                            break 2;
//                        }
//                    }
//                }
//            }
//        }
//
//        return $missingFields;
//    }
//
//    /**
//     * @phpstan-param  FieldDefinitions  $fieldDefinitions
//     *
//     * @return string[]
//     */
//    private function validateTrimmedFields(SimpleXMLElement $xml, array $fieldDefinitions): array
//    {
//        $untrimmedFields = [];
//
//        foreach ($fieldDefinitions as $fieldKey => $fieldDefinition) {
//            $nodes = $xml->xpath($fieldDefinition['path']);
//
//            if (! $nodes) {
//                continue;
//            }
//
//            foreach ($nodes as $node) {
//                if ((string) $node !== trim((string) $node)) {
//                    $untrimmedFields[] = $fieldKey;
//                    break;
//                }
//            }
//        }
//
//        return $untrimmedFields;
//    }
//
//    /**
//     * @phpstan-param  FieldDefinitions  $fieldDefinitions
//     * @phpstan-param  FieldType  $type
//     *
//     * @return string[]
//     */
//    private function validateTypedFields(SimpleXMLElement $xml, array $fieldDefinitions, string $type, callable $validator): array
//    {
//        $invalidFields = [];
//
//        foreach ($fieldDefinitions as $fieldKey => $fieldDefinition) {
//            if ($fieldDefinition['type'] === $type) {
//                $nodes = $xml->xpath($fieldDefinition['path']);
//
//                if (! $nodes) {
//                    continue;
//                }
//
//                foreach ($nodes as $node) {
//                    if (! $validator((string) $node)) {
//                        $invalidFields[] = $fieldKey;
//                        break;
//                    }
//                }
//            }
//        }
//
//        return $invalidFields;
//    }
// }
