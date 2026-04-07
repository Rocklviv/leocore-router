<?php

declare(strict_types=1);

namespace App\Router;

use Attribute;

/**
 * #[Route] attribute for marking controller methods as routable endpoints.
 *
 * Usage:
 *   #[Route('/users/{id}', methods: ['GET'])]
 *   public function show(int $id): Response { ... }
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class RouteAttribute
{
    private string $path;
    private array $methods;
    private ?string $name;
    private bool $caseSensitive;

    /**
     * @param string        $path          URL pattern, must start with '/'
     * @param array<string> $methods       Allowed HTTP verbs (default: ['GET'])
     * @param string|null   $name          Optional route name for reverse routing
     * @param bool          $caseSensitive Whether path matching is case-sensitive
     */
    public function __construct(
        string $path,
        array $methods = ['GET'],
        ?string $name = null,
        bool $caseSensitive = true,
    ) {
        if ($path === '') {
            throw new \InvalidArgumentException('Route path cannot be empty.');
        }

        if ($path[0] !== '/') {
            throw new \InvalidArgumentException('Route path must start with "/".');
        }

        $normalized = [];
        foreach ($methods as $method) {
            $upper = strtoupper((string) $method);
            if (!in_array($upper, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)) {
                throw new \InvalidArgumentException("Invalid HTTP method: \"{$method}\".");
            }
            $normalized[] = $upper;
        }

        if (empty($normalized)) {
            throw new \InvalidArgumentException('At least one HTTP method must be specified.');
        }

        $this->path          = $path;
        $this->methods       = array_values(array_unique($normalized));
        $this->name          = $name;
        $this->caseSensitive = $caseSensitive;
    }

    public function getPath(): string        { return $this->path; }
    public function getMethods(): array      { return $this->methods; }
    public function getName(): ?string       { return $this->name; }
    public function isCaseSensitive(): bool  { return $this->caseSensitive; }
}
