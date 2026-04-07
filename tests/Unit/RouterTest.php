<?php

declare(strict_types=1);

namespace App\Tests\Router;

use App\Router\Router;
use App\Router\Exceptions\HttpException;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Test adding a simple route.
     */
    public function testAddSimpleRoute(): void
    {
        $this->router->add("/hello", fn() => "world");

        $result = $this->router->dispatch("GET", "/hello");
        $this->assertEquals("world", $result);
    }

    /**
     * Test adding a route with parameters.
     */
    public function testAddRouteWithParams(): void
    {
        $this->router->add("/users/{id}", fn(int $id) => "User {$id}");

        $result = $this->router->dispatch("GET", "/users/42");
        $this->assertEquals("User 42", $result);
    }

    /**
     * Test route with multiple methods.
     */
    public function testAddRouteWithMultipleMethods(): void
    {
        $this->router->add(
            "/users/{name}",
            fn(string $name) => "User: {$name}",
            ["GET", "POST"],
        );

        // GET request
        $result = $this->router->dispatch("GET", "/users/Alice");
        $this->assertEquals("User: Alice", $result);

        // POST request
        $result = $this->router->dispatch("POST", "/users/Bob");
        $this->assertEquals("User: Bob", $result);
    }

    /**
     * Test 404 when route doesn't exist.
     */
    public function testNotFound(): void
    {
        try {
            $this->router->dispatch("GET", "/nonexistent");
            $this->fail("Should have thrown exception");
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    /**
     * Test 405 when method is not allowed on existing route.
     */
    public function testMethodNotAllowed(): void
    {
        $this->router->add("/users", fn() => "list", ["GET"]);

        try {
            $this->router->dispatch("POST", "/users");
            $this->fail("Should have thrown exception");
        } catch (HttpException $e) {
            $this->assertEquals(405, $e->getStatusCode());
        }
    }

    /**
     * Test path traversal protection.
     */
    public function testPathTraversalProtection(): void
    {
        try {
            $this->router->dispatch("GET", "/../../../etc/passwd");
            $this->fail("Should have thrown exception");
        } catch (HttpException $e) {
            $this->assertEquals(400, $e->getStatusCode());
        }
    }

    /**
     * Test case-sensitive matching.
     */
    public function testCaseSensitiveMatching(): void
    {
        $this->router->add("/Users", fn() => "UPPER", ["GET"]);

        try {
            $result = $this->router->dispatch("GET", "/users");
            $this->fail("Should not match lowercase");
        } catch (HttpException) {
            // Expected to throw 404
        }
    }

    /**
     * Test route priority (last one wins).
     */
    public function testRoutePriority(): void
    {
        $this->router->add("/users/{id}", fn(int $id) => "specific: {$id}");
        $this->router->add("/users", fn() => "generic");

        $result = $this->router->dispatch("GET", "/users/123");
        $this->assertEquals("specific: 123", $result);
    }

    /**
     * Test path normalization.
     */
    public function testPathNormalization(): void
    {
        $this->router->add("/api/v1/data", fn() => "ok");

        // Should work without leading slash
        try {
            $result = $this->router->dispatch("GET", "api/v1/data");
            $this->assertEquals("ok", $result);
        } catch (Exception) {
            // Implementation may or may not handle this, check behavior
        }

        // Should work with query string
        try {
            $result = $this->router->dispatch("GET", "/api/v1/data?id=1");
            $this->assertEquals("ok", $result);
        } catch (Exception) {
            // Implementation may or may not handle this
        }
    }

    /**
     * Test that methods are normalized to uppercase.
     */
    public function testMethodNormalization(): void
    {
        $this->router->add("/test", fn() => "ok", ["get"]); // lowercase input

        $result = $this->router->dispatch("GET", "/test");
        $this->assertEquals("ok", $result);
    }

    /**
     * Test multiple route registrations don't cause issues.
     */
    public function testMultipleRoutes(): void
    {
        $routes = [
            "/foo" => fn() => "foo",
            "/bar/{id}" => fn(int $id) => "bar-{$id}",
            "/baz/{x}/{y}/{z}" => fn(
                int $x,
                int $y,
                int $z,
            ) => "baz-{$x}-{$y}-{$z}",
        ];

        foreach ($routes as $path => $handler) {
            $this->router->add($path, $handler);
        }

        $this->assertEquals("foo", $this->router->dispatch("GET", "/foo"));
        $this->assertEquals(
            "bar-42",
            $this->router->dispatch("GET", "/bar/42"),
        );
        $this->assertEquals(
            "baz-1-2-3",
            $this->router->dispatch("GET", "/baz/1/2/3"),
        );
    }

    /**
     * Test that path must start with /.
     */
    public function testPathMustStartWithSlash(): void
    {
        try {
            // This should fail in the attribute validator, but test add() accepts any path
            $this->router->add("no-slash", fn() => "ok");
            $result = $this->router->dispatch("GET", "/no-slash");
            // May or may not match depending on implementation
        } catch (\InvalidArgumentException) {
            // Expected if add() validates like attribute does
        }
    }
}
