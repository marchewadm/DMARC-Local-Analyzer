<?php

declare(strict_types=1);

namespace App\Exceptions\Dmarc;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Exception thrown when an uploaded file is not a valid XML.
 */
final class InvalidXmlException extends Exception
{
    /**
     * Render a JSON response for the exception.
     *
     * @return JsonResponse A response with a 400 status code and error message.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The uploaded file is not a valid XML document.',
        ], 400);
    }
}
