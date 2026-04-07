<?php

declare(strict_types=1);

namespace App\Router\Middleware;

use App\Router\Exceptions\HttpException;

/**
 * Simple authentication middleware.
 */
class Auth
{
    public static function authenticate(?string $authorization = null): ?string
    {
        if (empty($authorization ?? '')) {
            throw new HttpException(401, 'Unauthorized', ['No authorization header provided']);
        }

        $token = $authorization;

        if (\substr($token, 0, 5) !== 'Bearer') {
            return null; // Allow unauthenticated routes
        }

        return \substr($token, 6); // Extract token after "Bearer "
    }
}
