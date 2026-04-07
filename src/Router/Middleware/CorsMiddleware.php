<?php

declare(strict_types=1);

namespace App\Router\Middleware;

/**
 * CORS middleware for handling Cross-Origin Resource Sharing.
 */
class CorsMiddleware
{
    public static function handle(array $options = []): callable
    {
        return function (callable $handler) use ($options): callable {
            return function (...$args) use ($handler, $options) {
                // Extract parameters from args if provided
                $params = array_slice($args, 1);

                $origin = $options['allowed_origins'] ?? ['*'];
                $allowedMethods = $options['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
                $allowedHeaders = $options['allowed_headers'] ?? ['Authorization', 'Content-Type'];
                $exposeHeaders = $options['exposed_headers'] ?? [];

                // Handle preflight OPTIONS requests
                if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                    self::addCorsHeaders([
                        'Allow' => implode(', ', array_unique(array_merge($allowedMethods, ['OPTIONS']))),
                        'Access-Control-Allow-Origin' => $origin[0] === '*' ? '*' : $_SERVER['HTTP_ORIGIN'],
                        'Access-Control-Allow-Methods' => implode(', ', $allowedMethods),
                        'Access-Control-Allow-Headers' => implode(', ', $allowedHeaders),
                    ]);
                    echo '';
                    return null;
                }

                // Add headers for non-preflight requests (unless Origin header is missing)
                if (!empty($_SERVER['HTTP_ORIGIN'])) {
                    self::addCorsHeaders([
                        'Access-Control-Allow-Origin' => $origin[0] === '*' ? '*' : $_SERVER['HTTP_ORIGIN'],
                        'Access-Control-Allow-Credentials' => ($options['allow_credentials'] ?? true) ? 'true' : 'false',
                        'Access-Control-Expose-Headers' => implode(', ', $exposeHeaders),
                    ]);
                }

                return $handler(...array_merge($args, array_values($params)));
            };
        };
    }

    private static function addCorsHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }
}
