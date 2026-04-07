<?php

declare(strict_types=1);

namespace App\Router\Middleware;

use App\Router\Response;

/**
 * Interface for middleware handlers.
 */
interface MiddlewareInterface
{
    /**
     * Handle the request and modify response as needed.
     *
     * @param array $requestContext Array containing method, headers, params, etc.
     * @param Response $response Current response object
     * @return Response Modified response
     */
    public function handle(array $requestContext, Response $response): Response;
}
