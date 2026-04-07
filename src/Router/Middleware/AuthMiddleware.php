<?php

declare(strict_types=1);

namespace App\Router\Middleware;

use App\Router\HttpException;

/**
 * Authentication middleware for route protection.
 */
class AuthMiddleware
{
    /**
     * Apply authentication to a handler.
     *
     * @param callable $handler Original handler
     * @return callable Wrapped handler
     */
    public function __invoke(callable $handler): callable
    {
        return fn($...$args) => self::authenticateRequest($args, $handler);
    }

    private static function authenticateRequest(array $args, callable $handler): mixed
    {
        if ($authToken = Auth::authenticate(mixed(...$args))) {
            // Token validated, proceed with handler
            return $handler(...$args);
        }

        throw new HttpException(401, 'Unauthorized', 'Authentication required');
    }
}
