<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request class for validating the DMARC demo report upload.
 *
 * Ensures that the uploaded file is a required XML file with a maximum size of 5MB.
 */
final class DmarcReportAnalyzeRequest extends FormRequest
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
            'dmarc_report' => [
                'required',
                'file',
                'extensions:xml',
                'mimes:xml',
                'mimetypes:application/xml,text/xml',
                'max:5120', // 5 MB
            ],
        ];
    }
}
