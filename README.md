# PHP Router

A lightweight, modern MVC router for PHP 8.2+ with attribute-based routing, middleware support, and secure dispatching.

## Features

- **Attribute-based routing** using `#[Route(path: '/path')]`
- **Manual route registration** via `$router->add()`
- **Controller discovery** with reflection scanning
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
git clone https://github.com/yourusername/php-Router.git
cd php-Router
```

## Usage Examples

### Attribute-based routing

```php
use App\Router\Router;
use App\Router\Response;

$router = new Router();
$router->discoverRoutes([UserController::class]);
```

Controller example:

```php
class UserController
{
    public function __construct(
        private Router $router,
        private string $basePath = ''
    ) {}

    #[Route('/users/{id}')]
    public function show(int $id): Response
    {
        // Automatic type casting from URL parameter
        $user = $this->getUser($id);
        return new Response('User #' . $id);
    }

    #[Route('/users', methods: ['GET', 'POST'])]
    public function list(): Response
    {
        // GET: list users, POST: create user
        return new Response('Users list');
    }
}
```

### Manual registration

```php
$router->add(
    '/api/data/{id}',
    [DataController::class, 'getData'],
    ['GET', 'DELETE']
);
```

### Middleware example

```php
use App\Router\Middleware\Csrf;
use App\Router\Middleware\Cors;

$router = new Router();

// Register CSRF middleware for state-changing routes
$router->add('/users', ['UserController::class', 'list'], ['GET'], [
    new Csrf()
]);

// Register CORS middleware
$router->add('/api/*', [ApiController::class, 'index'], ['GET'], [
    new Cors(['origin' => 'https://example.com'])
]);
```

## API Documentation

### Router Class

#### `__construct()`
Initialize the router.

#### `add(string $pattern, array $route, array $methods, ?array $middleware = null)`
Register a new route manually.

**Parameters:**
- `$pattern`: Route pattern (e.g., `/users/{id}`)
- `$route`: Array of [ControllerClass, method]
- `$methods`: Array of HTTP methods (GET, POST, PUT, etc.)
- `$middleware`: Optional array of middleware instances

#### `discoverRoutes(array $controllers)`
Automatically discover and register routes from controller classes.

**Parameters:**
- `$controllers`: Array of controller class names or strings

#### `dispatch(string $request): Response`
Dispatch a request to the matched route.

**Parameters:**
- `$request`: Request URI string

**Returns:** `Response` object

#### `getRoutes(): array`
Get all registered routes.

#### `getRoute(string $pattern): ?array`
Get a specific route by pattern.

### Response Class

#### `__construct(string $content = '', int $status = 200)`
Create a new response.

**Parameters:**
- `$content`: Response content
- `$status`: HTTP status code

#### `getContent(): string`
Get response content.

#### `setContent(string $content): self`
Set response content.

#### `getStatus(): int`
Get HTTP status code.

#### `setStatus(int $status): self`
Set HTTP status code.

#### `send()`
Send the response and exit.

### RouteAttribute Class

#### `#[Route(path: string, methods: array)]`
Attribute for attribute-based routing.

**Parameters:**
- `path`: Route pattern with optional parameters
- `methods`: Array of allowed HTTP methods (default: ['GET'])

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
- **Secure file loading**: Prevents includes outside app directory
- **Input sanitization**: Type casting and whitelist validation

## License

MIT License - see [LICENSE](LICENSE) file for details.
