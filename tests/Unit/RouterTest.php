<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Router\Router;
use App\Router\Response;

class RouterTest extends \PHPUnit\Framework\TestCase
{
    public function testSimpleRoute(): void
    {
        $router = new Router();
        $router->add('/health', fn() => new Response('OK', 200));

        $response = $router->dispatch('GET', '/health');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function testRouteWithPathParams(): void
    {
        $router = new Router();
        $router->add('/users/{id}', fn(int $id) => new Response("User #{$id}", 200));

        $response = $router->dispatch('GET', '/users/123');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('User #123', $response->getContent());
    }

    public function testRouteWithMultipleParams(): void
    {
        $router = new Router();
        $router->add('/users/{userId}/posts/{postId}', fn(int $userId, int $postId) => 
            new Response("Post #{$postId} by user #{$userId}", 200));

        $response = $router->dispatch('GET', '/users/42/posts/99');
        $this->assertEquals('Post #99 by user #42', $response->getContent());
    }

    public function testRouteNotFound(): void
    {
        $router = new Router();
        $router->add('/health', fn() => new Response('OK', 200));

        $this->expectException(\App\Router\Exceptions\HttpException::class);
        $response = $router->dispatch('GET', '/nonexistent');
    }

    public function testMethodNotAllowed(): void
    {
        $router = new Router();
        $router->add('/users', fn() => new Response('Users', 200), ['GET']);

        $this->expectException(\App\Router\Exceptions\HttpException::class);
        $response = $router->dispatch('POST', '/users');
    }

    public function testPathTraversalProtection(): void
    {
        $router = new Router();
        $router->add('/files/{file}', fn(string $file) => new Response($file, 200));

        $this->expectException(\App\Router\Exceptions\HttpException::class);
        $response = $router->dispatch('GET', '/files/../../../etc/passwd');
    }

    public function testRouteWithMiddleware(): void
    {
        $router = new Router();
        
        $called = 0;
        $router->add('/test', function() use (&$called) {
            $called++;
            return new Response('test', 200);
        }, ['GET']);

        $response = $router->dispatch('GET', '/test');
        $this->assertTrue($called > 0);
    }

    public function testRouteDump(): void
    {
        $router = new Router();
        $router->add('/users/{id}', fn(int $id) => new Response('User', 200));

        $routes = $router->dumpRoutes();
        $this->assertIsArray($routes);
        $this->assertCount(1, $routes);
        $this->assertEquals('/users/{id}', $routes[0]['path']);
    }

    public function testJsonResponse(): void
    {
        $router = new Router();
        $router->add('/api/data', fn() => Response::json(['status' => 'ok'], 200));

        $response = $router->dispatch('GET', '/api/data');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringStartsWith('{', $response->getContent());
    }

    public function testRedirectResponse(): void
    {
        $router = new Router();
        $router->add('/old', fn() => Response::redirect('/new'), ['GET']);

        $response = $router->dispatch('GET', '/old');
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/new', $response->getHeaders()['Location']);
    }

    public function testMultipleMethods(): void
    {
        $router = new Router();
        $router->add('/users', fn() => new Response('List', 200), ['GET']);
        $router->add('/users', fn() => new Response('Create', 201), ['POST']);

        $responseGet = $router->dispatch('GET', '/users');
        $this->assertEquals('List', $responseGet->getContent());

        $responsePost = $router->dispatch('POST', '/users');
        $this->assertEquals('Create', $responsePost->getContent());
    }

    public function testPathNormalization(): void
    {
        $router = new Router();
        $router->add('/health', fn() => new Response('OK', 200));

        // Test with double slashes
        $response = $router->dispatch('GET', '//health');
        $this->assertEquals('OK', $response->getContent());

        // Test with query string
        $response = $router->dispatch('GET', '/health?foo=bar');
        $this->assertEquals('OK', $response->getContent());
    }
}
