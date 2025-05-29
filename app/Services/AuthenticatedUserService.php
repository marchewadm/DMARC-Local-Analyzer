<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class AuthenticatedUserService
{
    /**
     * Retrieve the authenticated user's profile.
     *
     * @param  Request  $request  The current HTTP request containing the authenticated user.
     * @return User The authenticated user instance.
     */
    public function profile(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * Delete the authenticated user's account along with all their API tokens.
     *
     * @param  Request  $request  The current HTTP request containing the authenticated user.
     */
    public function delete(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();

        $user->tokens()->delete();
        $user->delete();
    }
}
