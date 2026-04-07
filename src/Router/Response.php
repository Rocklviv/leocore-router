<?php

declare(strict_types=1);

namespace App\Router;

/**
 * Secure, immutable HTTP response builder.
 *
 * Automatically sets security headers on every response.
 */
class Response
{
    private int $statusCode = 200;
    private string $content = '';
    /** @var array<string,string> */
    private array $headers = [];

    // -------------------------------------------------------------------------
    // Static constructors
    // -------------------------------------------------------------------------

    /**
     * Build a JSON response.
     *
     * @param array<mixed>|object $data
     */
    public static function json(array|object $data, int $statusCode = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            $encoded = json_encode(['error' => true, 'message' => 'JSON encoding failed']);
        }

        return (new self())
            ->write((string) $encoded)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($statusCode);
    }

    /**
     * Build a full HTML page response.
     */
    public static function html(string $html, string $title = 'Page', int $statusCode = 200): self
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n"
              . "  <meta charset=\"UTF-8\">\n"
              . "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
              . "  <title>{$safeTitle}</title>\n"
              . "</head>\n<body>\n{$html}\n</body>\n</html>";

        return (new self())
            ->write($body)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus($statusCode);
    }

    /**
     * Build a plain-text response.
     */
    public static function text(string $text, int $statusCode = 200): self
    {
        return (new self())
            ->write(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withStatus($statusCode);
    }

    /**
     * Build an empty 204 No Content response.
     */
    public static function empty(): self
    {
        return (new self())->withStatus(204);
    }

    /**
     * Build a redirect response.
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        $safeUrl = filter_var($url, FILTER_SANITIZE_URL) ?: '/';

        return (new self())
            ->withHeader('Location', $safeUrl)
            ->withStatus($statusCode);
    }

    // -------------------------------------------------------------------------
    // Fluent mutators (return clones to remain immutable)
    // -------------------------------------------------------------------------

    public function withStatus(int $code): self
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        return $clone;
    }

    /**
     * Set (or replace) a single response header.
     */
    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * Remove a header by name.
     */
    public function withoutHeader(string $name): self
    {
        $clone = clone $this;
        unset($clone->headers[$name]);
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @return array<string,string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Send the response to the client.
     *
     * Emits the HTTP status code, all stored headers, the mandatory security
     * headers, and finally the body.  Returns the body string so callers can
     * inspect it (useful in tests / CLI contexts).
     */
    public function send(): string
    {
        if (\PHP_SAPI !== 'cli') {
            http_response_code($this->statusCode);

            // Mandatory security headers (applied to every response)
            $securityHeaders = [
                'X-Frame-Options'        => 'DENY',
                'X-Content-Type-Options' => 'nosniff',
                'X-XSS-Protection'       => '1; mode=block',
                'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            ];

            foreach ($securityHeaders as $name => $value) {
                // Only set if the caller has not already customised the header
                if (!isset($this->headers[$name])) {
                    header("{$name}: {$value}");
                }
            }

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        // 1xx / 204 / 304 must not carry a body
        if (in_array($this->statusCode, [100, 101, 204, 304], true)) {
            return '';
        }

        if (\PHP_SAPI !== 'cli') {
            echo $this->content;
        }

        return $this->content;
    }

    /**
     * Allow casting the response to a string (outputs the body).
     */
    public function __toString(): string
    {
        return $this->send();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function write(string $content): self
    {
        $clone = clone $this;
        $clone->content = $content;
        return $clone;
    }
}
