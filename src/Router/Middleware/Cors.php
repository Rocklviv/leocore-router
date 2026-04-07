<?php

declare(strict_types=1);

namespace App\Router\Middleware;

use App\Router\Response;
use App\Router\HttpException;

/**
 * CORS Middleware.
 *
 * Configurable middleware to attach CORS headers to the response.
 */
class Cors
{
    private array $config = [
        "origin" => ["*"], // Stored as an array of allowed origins
        "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "headers" => ["Content-Type", "Authorization"],
    ];

    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Process the request, modifying headers if necessary.
     *
     * @param string $method The HTTP method being used.
     * @param string $origin The origin of the request.
     * @return Response The response object after header modification.
     */
    public function handle(
        string $method,
        string $origin,
        Response $response,
    ): Response {
        // 1. Check origin match
        $allowedOrigins = $this->config["origin"];

        if (!in_array($origin, $allowedOrigins, true)) {
            // If the origin is not explicitly allowed and the config doesn't allow '*',
            // we can choose to throw a 403 or simply omit the Access-Control-Allow-Origin header.
            // For robustness, we omit the header if the origin is not whitelisted.
        } else {
            // Set Access-Control-Allow-Origin to the requested origin if it's whitelisted
            $response = $response->withHeader(
                "Access-Control-Allow-Origin",
                $origin,
            );
        }

        // 2. Set Access-Control-Allow-Methods
        $response = $response->withHeader(
            "Access-Control-Allow-Methods",
            implode(", ", $this->config["methods"]),
        );

        // 3. Set Access-Control-Allow-Headers
        $response = $response->withHeader(
            "Access-Control-Allow-Headers",
            implode(", ", $this->config["headers"]),
        );

        // 4. Handle Preflight Request (OPTIONS)
        if ($method === "OPTIONS") {
            // For OPTIONS, we return 200 OK with headers and no body content
            return Response::empty()
                ->withStatus(200)
                ->withHeader("Access-Control-Max-Age", "86400");
        }

        return $response;
    }
}
