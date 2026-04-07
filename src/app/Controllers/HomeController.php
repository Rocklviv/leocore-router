<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Router\Response;
use App\Router\RouteAttribute as Route;
use App\Router\Exceptions\HttpException;

/**
 * Example controller for testing the router stack.
 */
class HomeController
{
    /**
     * Test route that returns a successful health check response.
     *
     * @return Response
     */
    #[Route("/health", methods: ["GET"])]
    public function healthCheck(): Response
    {
        return Response::text("OK - System Operational", 200);
    }

    /**
     * Test route that accepts parameters.
     *
     * @param int $id The ID of the resource.
     * @return Response
     */
    #[Route("/users/{id}", methods: ["GET"])]
    public function showUser(int $id): Response
    {
        return Response::json([
            "message" => "User details retrieved successfully.",
            "user_id" => $id,
        ]);
    }

    /**
     * Test route that requires a POST request.
     *
     * @return Response
     */
    #[Route("/submit", methods: ["POST"])]
    public function submitForm(): Response
    {
        // In a real scenario, we would read the body here for validation
        return Response::json(
            [
                "status" => "success",
                "message" =>
                    "Form submitted successfully (token validated by middleware).",
            ],
            201,
        );
    }
}
