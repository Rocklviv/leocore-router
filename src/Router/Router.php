<?php

declare(strict_types=1);

namespace App\Router;

use App\Router\Exceptions\HttpException;
use App\Router\Middleware\MiddlewareInterface;

/**
 * Lightweight PHP 8.2+ routing library with pattern matching, parameter extraction,
 * middleware support, and secure dispatching.
 *
 * Usage:
 *   $router = new Router();
 *   $router->add('/users', fn() => new Response("Users", 200));
 *   $router->add('/users/{id}', fn(int $id) => new Response("User #{$id}", 200));
 *   $response = $router->dispatch('GET', '/users/123');
 */
class Router
{
    /**
     * @var array<int,array{regex:string,methods:array,path:string,handler:callable|array|string,middleware:array<MiddlewareInterface>}>
     * Compiled routes with middleware pipeline.
     */
    private array $routes = [];

    private int $index = 0;

    /**
     * Register a route manually.
     *
     * @param string                     $path       URL pattern (e.g. '/users/{id}')
     * @param callable|array|string      $handler    Closure, ['Class','method'], or 'Class::method'
     * @param array<string>              $methods    Allowed HTTP methods
     * @param array<MiddlewareInterface> $middleware Middleware pipeline to run.
     */
    public function add(
        string $path,
        callable|array|string $handler,
        array $methods = ["GET"],
        array $middleware = [],
    ): self {
        if ($path === "" || $path[0] !== "/") {
            throw new \InvalidArgumentException(
                "Route path must start with \"/\": \"{$path}\"",
            );
        }

        $methods = array_map("strtoupper", array_filter($methods));

        foreach ($methods as $method) {
            if (
                !in_array(
                    $method,
                    ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
                    true,
                )
            ) {
                throw new \InvalidArgumentException(
                    "Invalid HTTP method: {$method}",
                );
            }
        }

        $this->routes[$this->index++] = [
            "regex" => $this->compilePattern($path),
            "methods" => array_values(array_unique($methods)),
            "path" => $path,
            "handler" => $handler,
            "middleware" => $middleware,
        ];

        return $this;
    }

    /**
     * Dispatch an incoming request to the matching route handler.
     *
     * @param string                    $method  HTTP verb (GET, POST, …)
     * @param string                    $path    Request path, without query string
     * @param array<string,string>|null $headers Optional headers; injected for testing in CLI environments
     * @return Response The final response object.
     */
    public function dispatch(
        string $method,
        string $path,
        ?array $headers = null,
    ): Response {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        // Detect path-traversal attempts or null bytes
        if ($this->isMaliciousPath($path)) {
            throw new HttpException(
                400,
                "Bad Request: path traversal attempt detected",
            );
        }

        // 1. Find all routes whose pattern matches the requested path
        $pathMatches = [];
        foreach ($this->routes as $route) {
            if (preg_match($route["regex"], $path, $matches)) {
                $pathMatches[] = ["route" => $route, "matches" => $matches];
            }
        }

        if (empty($pathMatches)) {
            throw new HttpException(
                404,
                "Not Found: no route matched the requested path",
            );
        }

        // 2. Filter by HTTP method
        $methodMatches = [];
        foreach ($pathMatches as $match) {
            if (in_array($method, $match["route"]["methods"], true)) {
                $methodMatches[] = $match;
            }
        }

        if (empty($methodMatches)) {
            // Collect all allowed methods from path-matching routes
            $routeArrays = array_column($pathMatches, "route");
            $allowed = array_unique(
                array_merge(...array_column($routeArrays, "methods")),
            );
            throw new HttpException(
                405,
                "Method Not Allowed. Allowed: " . implode(", ", $allowed),
            );
        }

        // 3. Select the best matching route (longest static path first)
        usort(
            $methodMatches,
            fn($a, $b) => strlen($b["route"]["path"]) -
                strlen($a["route"]["path"]),
        );
        ["route" => $route, "matches" => $matches] = $methodMatches[0];

        // 4. Build request context for the middleware pipeline
        $response = new Response();
        $requestContext = [
            "method" => $method,
            "path" => $path,
            "params" => array_slice($matches, 1),
            "body" => (string) file_get_contents("php://input"),
            "headers" =>
                $headers ??
                (function_exists("getallheaders") ? getallheaders() : []),
        ];

        // 5. Invoke the matched handler first
        $handlerResult = $this->invokeHandler($route, $matches);

        // 6. Wrap scalar returns in a Response object
        if ($handlerResult instanceof Response) {
            $currentResponse = $handlerResult;
        } else {
            $currentResponse = Response::text((string) $handlerResult);
        }

        // 7. Execute middleware pipeline on the handler response
        foreach ($route["middleware"] as $mw) {
            try {
                $currentResponse = $mw->handle(
                    $requestContext,
                    $currentResponse,
                );
            } catch (HttpException $e) {
                return $e->render();
            }
        }

        // 8. Return the final response
        return $currentResponse;
    }

    /**
     * Compile a URL pattern such as '/users/{id}' into a named-capture regex.
     */
    private function compilePattern(string $pattern): string
    {
        $escaped = preg_quote($pattern, "#");

        // preg_quote escapes { and } — restore them so we can match placeholders
        $escaped = str_replace(["\{", "\}"], ["{", "}"], $escaped);

        // Replace {param} with a named capture group
        $regex = preg_replace("/\{(.*?)\}/", '(?P<\1>[^/]*)', $escaped);

        return "#^" . $regex . '$#u';
    }

    /**
     * Strip the query string and ensure a leading slash.
     */
    private function normalizePath(string $path): string
    {
        if (($pos = strpos($path, "?")) !== false) {
            $path = substr($path, 0, $pos);
        }

        return "/" . ltrim($path, "/");
    }

    /**
     * Detect path-traversal attempts or null bytes.
     */
    private function isMaliciousPath(string $path): bool
    {
        return str_contains($path, "../") ||
            str_contains($path, "..\\") ||
            str_contains($path, "\x00") ||
            str_contains($path, "%00");
    }

    /**
     * Invoke the matched route's handler with type-cast named parameters.
     */
    private function invokeHandler(array $route, array $matches): mixed
    {
        $handler = $route["handler"];
        $namedParams = array_filter(
            $matches,
            fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY,
        );

        // --- Closure / callable ---
        if ($handler instanceof \Closure) {
            $reflection = new \ReflectionFunction($handler);
            $typedParams = [];

            foreach ($reflection->getParameters() as $param) {
                $name = $param->getName();

                if (!array_key_exists($name, $namedParams)) {
                    if ($param->isOptional()) {
                        $typedParams[] = $param->getDefaultValue();
                    }
                    continue;
                }

                $value = $namedParams[$name];
                $type = $param->getType();
                $typeName =
                    $type instanceof \ReflectionNamedType
                        ? $type->getName()
                        : "string";

                $typedParams[] = match ($typeName) {
                    "int", "integer" => (int) $value,
                    "float", "double" => (float) $value,
                    "bool", "boolean" => filter_var(
                        $value,
                        FILTER_VALIDATE_BOOLEAN,
                    ),
                    default => (string) $value,
                };
            }

            return $handler(...$typedParams);
        }

        // --- ['ClassName', 'methodName'] or 'ClassName::methodName' ---
        if (is_array($handler) && count($handler) === 2) {
            [$class, $methodName] = $handler;
        } elseif (is_string($handler) && str_contains($handler, "::")) {
            [$class, $methodName] = explode("::", $handler, 2);
        } else {
            throw new \InvalidArgumentException(
                "Unsupported handler format provided to router.",
            );
        }

        if (!is_string($class) || !class_exists($class)) {
            throw new HttpException(
                500,
                "Handler class not found: {$class}",
            );
        }

        if (!method_exists($class, $methodName)) {
            throw new HttpException(
                404,
                "Method {$methodName} not found on {$class}",
            );
        }

        $reflection = new \ReflectionMethod($class, $methodName);
        $typedParams = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();

            if (!array_key_exists($name, $namedParams)) {
                if ($param->isOptional()) {
                    $typedParams[] = $param->getDefaultValue();
                }
                continue;
            }

            $value = $namedParams[$name];
            $type = $param->getType();
            $typeName =
                $type instanceof \ReflectionNamedType
                    ? $type->getName()
                    : "string";

            $typedParams[] = match ($typeName) {
                "int", "integer" => (int) $value,
                "float", "double" => (float) $value,
                "bool", "boolean" => filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN,
                ),
                default => (string) $value,
            };
        }

        return $class::$methodName(...$typedParams);
    }

    /**
     * Return all registered routes for debugging or inspection.
     *
     * @return array<int,array{regex:string,methods:array,path:string,handler:callable|array|string,middleware:array<MiddlewareInterface>}>
     */
    public function dumpRoutes(): array
    {
        return $this->routes;
    }
}
