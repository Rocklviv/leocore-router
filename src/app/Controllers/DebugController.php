<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Router\Router;
use App\Router\Response;
use JsonException;

/**
 * DebugController exposes internal router information for diagnostic purposes.
 */
class DebugController
{
    private Router $router;

    /**
     * Inject the Router instance.
     *
     * @param Router $router The main router instance.
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * Lists all registered routes and returns them as a JSON HTTP response.
     *
     * This endpoint is intended for debugging and should not be exposed in production without proper security measures.
     *
     * @return Response A Response object containing the route map.
     */
    public function listRoutes(): Response
    {
        $routes = $this->router->dumpRoutes();

        try {
            $json = json_encode($routes, JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            // If encoding fails, return a generic server error response
            return new Response("Error encoding routes: " . $e->getMessage(), 500);
        }

        // Return the data wrapped in a Response object, setting appropriate headers
        // Note: We assume Response::text() can handle setting Content-Type if needed,
        // or we use a specific JSON helper if one existed. Sticking to text for safety.
        return new Response($json, 200, ['Content-Type' => 'application/json']);
    }
}
