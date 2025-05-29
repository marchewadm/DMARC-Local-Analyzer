<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthenticatedUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedUserController extends Controller
{
    public function __construct(private readonly AuthenticatedUserService $authenticatedUserService) {}

    /**
     * Display the authenticated user's profile.
     *
     * @param  Request  $request  The current HTTP request containing the authenticated user.
     * @return User The authenticated user instance.
     */
    public function show(Request $request): User
    {
        return $this->authenticatedUserService->profile($request);
    }

    /**
     * Delete the authenticated user's account and associated data.
     *
     * @param  Request  $request  The current HTTP request containing the authenticated user.
     * @return JsonResponse A JSON response confirming account deletion.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->authenticatedUserService->delete($request);

        return response()->json([
            'message' => 'Your account and all associated data have been deleted. You will now be signed out.',
        ]);
    }
}
