# PHP Router - Lightweight MVC Router for PHP 8.2+

A modern, secure PHP router with attribute-based routing, middleware support, and proper PSR-4 namespace structure.

## Features

- **Attribute-based routing** using `#[Route(path: '/path')]`
- **Manual route registration** via `$router->add()`
- **Controller discovery** with reflection scanning
- **Secure dispatching** with path traversal protection
- **Type-safe parameters** (int, float, bool casting)
- **Built-in middleware** for auth and CORS
- **Response builder** for JSON, HTML, text responses

## Quick Start

```php
require 'vendor/autoload.php';

use App\Router\Router;
use App\Controllers\UserController;

$router = new Router();
$router->discoverRoutes([UserController::class]);

// Or manually register:
$router->add('/users/{id}', [UserController::class, 'show'], ['GET']);
```

## Installation

```bash
composer require php-router/router
```

Or use directly (no dependencies needed):

```bash
git clone https://github.com/yourusername/php-Router.git
cd php-Router
php -S localhost:8000 public/index.php
```

## Usage Examples

### Attribute-based routing

```php
class UserController {
    #[Route('/users/{id}')]
    public function show(int $id): Response {
        // Automatic type casting from URL parameter
    }
    
    #[Route('/users', methods: ['GET', 'POST'])]
    public function list(): Response {}
}
```

### Manual registration

```php
$router->add('/api/data/{id}', 
    [MyController::class, 'getData'], 
    ['GET', 'DELETE']
);
```

## Security Features

- Path traversal protection (blocks `..`, null bytes)
- Method normalization to prevent injection
- Case-sensitive matching by default
- Null byte stripping in paths

## License

MIT
