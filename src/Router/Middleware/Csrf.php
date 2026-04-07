<?php

declare(strict_types=1);

namespace App\Router\Middleware;

use App\Router\Response;
use App\Router\Exceptions\HttpException;

/**
 * CSRF Token Middleware.
 *
 * Ensures that state-changing HTTP requests (POST, PUT, PATCH, DELETE)
 * include a valid CSRF token for security.
 */
class Csrf
{
    private const TOKEN_NAME = 'csrf_token';
    private const SESSION_KEY = 'csrf_token';

    /**
     * Process the request, validating tokens where necessary.
     *
     * @param string $method The HTTP method being used.
     * @param array $requestData The request body/headers containing the token.
     * @param Response $response The response object.
     * @return Response The response object after validation.
     */
    public function handle(string $method, array $requestData, Response $response): Response
    {
        $stateChangingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        // Only run validation on state-changing requests
        if (!in_array($method, $stateChangingMethods)) {
            return $this->generateTokenResponse($response);
        }

        // --- 1. Ensure Token Exists in Session ---
        // In a real environment, this assumes session_start() has been called upstream.
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        $sessionToken = $_SESSION[self::SESSION_KEY];

        // --- 2. Extract Token from Request ---
        // Check POST body first, then headers (common pattern)
        $requestToken = $requestData['csrf_token'] ?? $requestData['headers']['X-CSRF-Token'] ?? null;

        if (is_null($requestToken)) {
            throw new HttpException(403, 'CSRF Token Missing', '/');
        }

        // --- 3. Validate Token ---
        if (!hash_equals($sessionToken, $requestToken)) {
            // Token mismatch: Invalid request
            throw new HttpException(403, 'Invalid CSRF Token', '/');
        }

        // Token is valid for this request.
        return $response;
    }

    /**
     * Generates and injects the CSRF token into the response headers/body for the client.
     */
    private function generateTokenResponse(Response $response): Response
    {
        // Generate new token and store it in session
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        // Inject token into response headers for frontend consumption
        $response = $response->withHeader('X-CSRF-Token', $_SESSION[self::SESSION_KEY]);

        return $response;
    }
}
