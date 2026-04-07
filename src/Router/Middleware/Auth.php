<?php

declare(strict_types=1);

namespace App\Router\Middleware;

use App\Router\HttpException;

/**
 * Simple authentication middleware.
 */
class Auth
{
    public static function authenticate(mixed $request): ?string
    {
        if (empty($_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
            throw new HttpException(401, 'Unauthorized', 'No authorization header provided');
        }

        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (\substr($token, 0, 5) !== 'Bearer') {
            return null; // Allow unauthenticated routes
        }

        return \substr($token, 6); // Extract token after "Bearer "
    }
}
