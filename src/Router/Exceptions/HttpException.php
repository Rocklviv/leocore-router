<?php

declare(strict_types=1);

namespace App\Router\Exceptions;

use App\Router\Response;

/**
 * Lightweight HTTP exception extending PHP's Exception.
 */
class HttpException extends \ErrorException implements \Throwable
{
    private int $statusCode;
    private array $errors = [];

    public function __construct(int $statusCode, string $message = '', array $errors = [])
    {
        $this->statusCode = $statusCode;
        parent::__construct($message);
        if (!empty($errors)) {
            $this->errors = $errors;
        }
    }

    public function getStatusCode(): int { return $this->statusCode ?? 0; }
    public function getErrors(): array { return $this->errors; }

    public function render(array $options = []): Response
    {
        return match ($this->statusCode) {
            400 => Response::json([
                'error' => true,
                'message' => $this->getMessage(),
                'errors' => $this->errors ?? ['Bad Request'],
                'path' => $_SERVER['REQUEST_URI'] ?? '/null',
            ], 400),
            401 => Response::json([
                'error' => true,
                'message' => $this->getMessage(),
                'path' => $_SERVER['REQUEST_URI'] ?? '/null',
            ], 401),
            403 => Response::json([
                'error' => true,
                'message' => $this->getMessage(),
                'path' => $_SERVER['REQUEST_URI'] ?? '/null',
            ], 403),
            404 => Response::json([
                'error' => true,
                'message' => $this->getMessage(),
                'path' => $_SERVER['REQUEST_URI'] ?? '/null',
            ], 404),
            405 => Response::json([
                'error' => true,
                'message' => $this->getMessage(),
                'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                'path' => $_SERVER['REQUEST_URI'] ?? '/null',
            ], 405),
            default => Response::json([
                'error' => true,
                'message' => $this->getMessage(),
                'status' => $this->statusCode,
            ], $this->statusCode ?? 500),
        };
    }

    public function renderPlainText(): Response
    {
        $body = htmlspecialchars($this->getMessage()) . "\n\n";
        if ($this->errors) {
            $body .= json_encode($this->errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }
        return Response::text($body)->withStatus($this->statusCode ?? 500);
    }
}
