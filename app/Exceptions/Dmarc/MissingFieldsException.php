<?php

declare(strict_types=1);

namespace App\Exceptions\Dmarc;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception thrown when required DMARC fields are missing from the parsed XML.
 */
final class MissingFieldsException extends Exception
{
    /**
     * Render a JSON response for the exception.
     *
     * @return JsonResponse A response with a 400 status code and error message.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The uploaded XML is valid, but required DMARC fields are missing.',
        ], 400);
    }
}
