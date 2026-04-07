<?php

declare(strict_types=1);

// Bootstrap the router
require __DIR__ . "/../vendor/autoload.php";

use App\Router\Router;
use App\Router\Response;
use App\Router\Exceptions\HttpException;
use App\Router\Middleware\Cors;
use App\Router\Middleware\Csrf;
use App\Controllers\DebugController;
use App\Controllers;

// --- Setup ---
try {
    // 1. Initialize Components
    $router = new Router();
    $corsMiddleware = new Cors();
    $csrfMiddleware = new Csrf();

    // Instantiate DebugController and register the debug route
    $debugController = new DebugController($router);
    $router->add(
        "/debug/routes",
        fn() => $debugController->listRoutes(),
        ["GET"],
        [],
    );

    // 2. Discover Routes (Populates $router with registered routes)
    // We register the example controller here.
    $router->discoverRoutes([App\Controllers\HomeController::class]);
    $router->dumpRoutes();
    // 3. Get Request Context
    $method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
    $path = $_SERVER["REQUEST_URI"] ?? "/";

    // Strip query string
    if (($pos = strpos($path, "?")) !== false) {
        $path = substr($path, 0, $pos);
    }
    $dispatchPath = $path;

    // Gather request data needed by middlewares (e.g., headers, body)
    $requestHeaders = getallheaders();
    $requestBody = file_get_contents("php://input");

    // --- Middleware Pipeline Execution ---

    // Start with a default response
    $response = Response::empty()->withStatus(200);

    // 4. Apply CSRF Middleware (must run before dispatch/route matching for token generation)
    // CSRF middleware might throw 403 or modify the response by setting tokens.
    $response = $csrfMiddleware->handle(
        $method,
        [
            "csrf_token" => $_POST["csrf_token"] ?? null,
            "headers" => $requestHeaders,
        ],
        $response,
    );

    // 5. Apply CORS Middleware (usually runs after initial security checks)
    // We need to derive the origin from the request headers
    $origin = $_SERVER["HTTP_ORIGIN"] ?? "*";
    $response = $corsMiddleware->handle($method, $origin, $response);

    // 6. Dispatch to Router (The router now handles the actual routing and final response generation)
    // Since middleware already ran, we dispatch to get the final content.
    $finalResponse = $router->dispatch($method, $dispatchPath);

    // 7. Output Final Response
    if ($finalResponse instanceof Response) {
        $finalResponse->send();
    } else {
        // Fallback for scalar return from handler
        Response::text((string) $finalResponse)->send();
    }
} catch (HttpException $e) {
    // Handle known router/API errors (400, 403, 404, 405)
    $e->render()->send();
} catch (\Throwable $e) {
    // Handle unexpected server errors
    error_log($e->getMessage() . "\n" . $e->getTraceAsString());

    Response::json(
        [
            "error" => true,
            "message" => "Internal Server Error",
        ],
        500,
    )->send();
}
