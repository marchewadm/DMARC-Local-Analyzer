<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidDmarcReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request class for validating the upload of DMARC reports.
 *
 * Ensures that each uploaded file is a valid XML file with a maximum size of 5 MB.
 */
class StoreDmarcReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dmarc_reports' => [
                'required',
                'array',
                'max:10',
            ],
            'dmarc_reports.*' => [
                'file',
                'extensions:xml',
                'mimes:xml',
                'mimetypes:application/xml,text/xml',
                'max:5120', // 5 MB
                new ValidDmarcReport,
            ],
        ];
    }
}
