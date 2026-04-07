# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Production-ready, secure PHP 8+ router system with Docker integration for local development on macOS. Implements lightweight MVC without Symfony/Laravel dependencies.

## Architecture

### Directory Structure (Target Layout)
```
/Router/
├── public/                    # Public entry point directory
│   └── index.php             # Front controller
├── app/
│   ├── Controllers/          # Controller classes
│   │   └── HomeController.php
│   └── models/               # Model files
├── src/
│   └── Router/
│       ├── Router.php        # Core router with dispatch() method
│       ├── RouteAttribute.php # #[Route] attribute class
│       ├── Middleware/
│       │   ├── Csrf.php      # CSRF token middleware
│       │   └── Cors.php      # CORS header middleware
│       └── Response.php      # Secure response object
├── tests/Unit/
│   └── RouterTest.php        # PHPUnit test suite
├── composer.json             # Dependencies (phpunit, autoload)
├── docker-compose.yml        # Docker compose config
├── Dockerfile                # php:8.2-apache based
├── README.md                 # Setup instructions
└── CLAUDE.md                 # This file
```

### Core Components

**Router.php** (`src/Router/Router.php`):
- Main router class in namespace `App\Core\Router` or similar PSR-4 namespace
- `dispatch($request)` method handles URL matching and parameter extraction
- Route pattern compilation with regex (e.g., `/users/{id}` → `^/users/(?<id>[^/]+)$`)
- Extracts named parameters from matched routes
- Method validation: GET, POST, PUT, PATCH, DELETE

**RouteAttribute.php** (`src/Router/RouteAttribute.php`):
- #[Route] attribute for attribute-based routing
- Stores pattern and methods array
- Read-only immutable attribute

**Response.php** (`src/Router/Response.php`):
- Secure response object with automatic security headers
- `x-xss-protection: 1; mode=block`
- `x-frame-options: DENY`
- `content-security-policy` header support
- XSS prevention via `htmlspecialchars()` for output

**Middleware**:
- **Csrf.php**: Session-based CSRF token generation and validation, returns 403 for invalid tokens
- **Cors.php**: Configurable CORS headers per-route or global settings

### Router Workflow

1. Front controller (`public/index.php`) loads router
2. Parse `$_SERVER['REQUEST_URI']` and HTTP method
3. Match route pattern via regex compilation
4. Extract named parameters (id, name, etc.) from matched groups
5. Run middleware pipeline (CSRF → CORS)
6. Instantiate controller with extracted params
7. Call public method on controller
8. Return Response object

### Route Pattern Syntax

- `{param}` - Simple parameter capturing anything non-slash
- `/{path}/{optional?}` - Optional path segments
- Methods: GET, POST, PUT, PATCH, DELETE

## Security Requirements

All implementations must include:

| Check | Implementation |
|-------|----------------|
| Path traversal | Reject URLs containing `../` or `\\0` |
| CSRF protection | Session token storage, validate on state-changing ops |
| CORS | Configurable headers per-route or global |
| XSS prevention | `htmlspecialchars()` in Response::content() |
| Input sanitization | Type casting, whitelist validation |
| Secure file loading | Prevent includes outside app directory |

## Performance Targets

- Route matching: <1ms for typical apps (<50 routes)
- Memory usage: <20MB for router engine
- Startup time: <500ms for initial discovery
- Cache support: Optional route map caching

## Development Commands

### Local Development (macOS)
```bash
# Start Docker containers
docker-compose up --build -d

# View logs
docker-compose logs -f app

# Stop containers
docker-compose down
```

### Testing
```bash
# Run all tests
php vendor/bin/phpunit tests/

# Run single test file
php vendor/bin/phpunit tests/Unit/RouterTest.php

# Get coverage
php vendor/bin/phpunit --coverage-text tests/
```

### Building with Docker
```bash
docker-compose up --build -d
curl http://localhost:8080/
```

## Implementation Priority Order

1. **Phase 1: Core Router Logic** (Highest) - Route matching, param extraction
2. **Phase 2: Security Layer** (High) - CSRF, CORS, Response headers
3. **Phase 3: Controller System** (Medium) - Instantiation, validation, model loading
4. **Phase 4: Testing & Docs** (Medium) - PHPUnit tests, README

## Code Style Requirements

1. Type declarations with `declare(strict_types=1);`
2. Use exceptions instead of `@` suppression for errors
3. Use `error_log()` for production logging, not `echo`
4. Always set security headers in Response object
5. PSR-4 namespaces (e.g., `\App\Core\Router`)
6. Proper PHPDoc comments on all public methods

## Example Usage

### Controller with Route Attribute
```php
#[Route('/users/{id}', methods: ['GET'])]
public function show(int $id): Response {
    $user = $this->getUser($id);
    return new Response("User #$id", 200);
}
```

### Router Registration
```php
$router = new Router();
$router->registerController('App\Controllers\Users', '/users');
$response = $router->dispatch($request);
```

## Files to Create (Priority Order)

1. `src/Router/Router.php` - Core router logic
2. `src/Router/RouteAttribute.php` - Route attribute class
3. `src/Router/Middleware/Csrf.php` - CSRF middleware
4. `src/Router/Middleware/Cors.php` - CORS middleware
5. `src/Router/Response.php` - Secure response object
6. `src/app/Controllers/HomeController.php` - Example controller
7. `tests/Unit/RouterTest.php` - PHPUnit tests
8. `docker-compose.yml` - Docker compose config
9. `Dockerfile` - Optimized for PHP 8.2 Apache
10. `public/index.php` - Front controller entry point

## Acceptance Criteria

Complete when:
- All files exist with proper structure
- Tests passing (code coverage >80%)
- Security headers present on all responses
- Docker build succeeds without errors
- Route matching extracts params correctly
- CSRF returns 403 for invalid tokens
