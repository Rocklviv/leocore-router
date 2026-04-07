# PHP Router

A lightweight, modern PHP 8.2+ routing library with pattern matching, parameter extraction, middleware support, and secure dispatching.

## Features

- **Pattern-based routing** with named parameters (`{id}`, `{name}`, etc.)
- **Flexible handler registration** (closures, class methods, strings)
- **Secure dispatching** with path traversal protection
- **Type-safe parameters** (int, float, bool automatic casting)
- **Built-in middleware** for CSRF protection and CORS headers
- **Secure response builder** with XSS prevention
- **PSR-4 autoloading** compatible

## Installation

Via Composer:

```bash
composer require leocore/router
```

Or use directly (no dependencies needed):

```bash
git clone https://github.com/Rocklviv/leocore-router.git
cd leocore-router
```

## Usage Examples

### Simple closure handler

```php
use App\Router\Router;
use App\Router\Response;

$router = new Router();
$router->add('/health', fn() => new Response('OK - System Operational', 200));
```

### Handler with parameters

```php
$router->add('/users/{id}', fn(int $id) => new Response("User #{$id}", 200));
```

### Class method handler

```php
$router->add('/api/data/{id}', [DataHandler::class, 'getData'], ['GET', 'DELETE']);
```

### Multiple HTTP methods

```php
$router->add('/users', fn() => new Response('Users list'), ['GET']);
$router->add('/users', fn() => new Response('Create user', 201), ['POST']);
```

### Middleware example

```php
use App\Router\Middleware\Csrf;
use App\Router\Middleware\Cors;

$router = new Router();

// Register CSRF middleware for state-changing routes
$router->add('/users', fn() => new Response('Users'), ['GET'], [
    new Csrf()
]);

// Register CORS middleware
$router->add('/api/*', fn() => new Response('API'), ['GET'], [
    new Cors(['origin' => 'https://example.com'])
]);
```

### Dispatching requests

```php
$router->add('/users/{id}', fn(int $id) => new Response("User #{$id}", 200));

// Dispatch a request
$response = $router->dispatch('GET', '/users/123');
echo $response->getContent(); // Outputs: User #123
```

## API Documentation

### Router Class

#### `__construct()`
Initialize the router.

#### `add(string $path, callable|array|string $handler, array $methods = ['GET'], array $middleware = [])`
Register a new route manually.

**Parameters:**
- `$path`: Route pattern (e.g., `/users/{id}`)
- `$handler`: Closure, array of `[ClassName, method]`, or string `'ClassName::method'`
- `$methods`: Array of HTTP methods (GET, POST, PUT, etc.)
- `$middleware`: Optional array of middleware instances

#### `dispatch(string $method, string $path, ?array $headers = null): Response`
Dispatch a request to the matched route.

**Parameters:**
- `$method`: HTTP method (GET, POST, PUT, DELETE, PATCH, OPTIONS)
- `$path`: Request path (without query string)
- `$headers`: Optional array of headers (for CLI/testing)

**Returns:** `Response` object

#### `dumpRoutes(): array`
Get all registered routes for debugging.

**Returns:** Array of route configurations

### Middleware

#### `Csrf`
CSRF token generation and validation middleware. Returns 403 for invalid tokens.

#### `Cors`
CORS header middleware with configurable origin and methods.

## Security Features

- **Path traversal protection**: Blocks `..` and null bytes in URLs
- **Method normalization**: Prevents HTTP method injection
- **CSRF protection**: Session-based token validation for state-changing operations
- **CORS control**: Configurable per-route or global CORS headers
- **XSS prevention**: `htmlspecialchars()` on all response content
- **Input sanitization**: Type casting and whitelist validation

## License

MIT License - see [LICENSE](LICENSE) file for details.
