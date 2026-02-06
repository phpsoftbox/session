<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Cookie\SameSite;
use PhpSoftBox\Cookie\SetCookie;
use PhpSoftBox\Session\Exception\CsrfTokenMismatchException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function bin2hex;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function preg_quote;
use function random_bytes;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtoupper;
use function trim;

final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @param list<string> $methods
     */
    public function __construct(
        private readonly SessionInterface $session,
        private readonly string $sessionKey = 'csrf_token',
        private readonly string $inputKey = '_token',
        private readonly string $headerKey = 'X-XSRF-TOKEN',
        private readonly array $methods = ['POST', 'PUT', 'PATCH', 'DELETE'],
        private readonly bool $regenerate = false,
        private readonly string $attribute = 'csrf_token',
        private array $excludedPaths = [],
        private readonly string $cookieName = 'XSRF-TOKEN',
        private readonly ?string $cookiePath = '/',
        private readonly ?string $cookieDomain = null,
        private readonly ?SameSite $cookieSameSite = SameSite::Lax,
        private readonly ?bool $cookieSecure = null,
        private readonly bool $cookieHttpOnly = false,
        private readonly string $cookieQueueAttribute = 'cookie_queue',
    ) {
    }

    /**
     * @param list<string> $paths
     */
    public function except(array $paths): self
    {
        $clone                = clone $this;
        $clone->excludedPaths = $paths;

        return $clone;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isExcluded($request)) {
            return $handler->handle($request);
        }

        $this->session->start();

        $token = $this->session->get($this->sessionKey);
        if (!is_string($token) || $token === '') {
            $token = $this->generateToken();
            $this->session->set($this->sessionKey, $token);
        }

        $request = $request->withAttribute($this->attribute, $token);

        $method = strtoupper($request->getMethod());
        if (in_array($method, $this->methods, true)) {
            $provided = $this->extractToken($request);
            if ($provided === null || !hash_equals($token, $provided)) {
                throw new CsrfTokenMismatchException();
            }

            if ($this->regenerate) {
                $token = $this->generateToken();
                $this->session->set($this->sessionKey, $token);
                $request = $request->withAttribute($this->attribute, $token);
            }
        }

        $response = $handler->handle($request);

        $this->attachCookie($request, $response, $token);

        return $response;
    }

    private function attachCookie(ServerRequestInterface $request, ResponseInterface $response, string $token): ResponseInterface
    {
        if ($this->cookieName === '') {
            return $response;
        }

        $cookie = SetCookie::create($this->cookieName, $token)
            ->withPath($this->cookiePath)
            ->withDomain($this->cookieDomain)
            ->withHttpOnly($this->cookieHttpOnly)
            ->withSameSite($this->cookieSameSite);

        $secure = $this->cookieSecure ?? ($request->getUri()->getScheme() === 'https');
        $cookie = $cookie->withSecure($secure);

        $queue = $request->getAttribute($this->cookieQueueAttribute);
        if ($queue instanceof CookieQueue) {
            $queue->queue($cookie);

            return $response;
        }

        return $response->withAddedHeader('Set-Cookie', $cookie->toHeader());
    }

    private function isExcluded(ServerRequestInterface $request): bool
    {
        if ($this->excludedPaths === []) {
            return false;
        }

        $path    = $request->getUri()->getPath();
        $fullUrl = $this->fullUrl($request);

        foreach ($this->excludedPaths as $pattern) {
            $pattern = trim((string) $pattern);
            if ($pattern === '') {
                continue;
            }

            $value = $this->isAbsolutePattern($pattern) ? $fullUrl : $path;
            if ($this->matchesPattern($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function isAbsolutePattern(string $pattern): bool
    {
        return str_starts_with($pattern, 'http://') || str_starts_with($pattern, 'https://');
    }

    private function matchesPattern(string $pattern, string $value): bool
    {
        if ($pattern === $value) {
            return true;
        }

        if (!str_contains($pattern, '*')) {
            return false;
        }

        $regex = '#^' . str_replace('\\*', '.*', preg_quote($pattern, '#')) . '$#';

        return preg_match($regex, $value) === 1;
    }

    private function fullUrl(ServerRequestInterface $request): string
    {
        $uri    = $request->getUri();
        $scheme = $uri->getScheme();
        $host   = $uri->getHost();
        $port   = $uri->getPort();

        $authority = $host;
        if ($port !== null && $port !== 80 && $port !== 443) {
            $authority .= ':' . $port;
        }

        $prefix = $scheme !== '' ? $scheme . '://' : '';

        return $prefix . $authority . $uri->getPath();
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine($this->headerKey);
        if ($header !== '') {
            return $header;
        }

        if ($this->headerKey !== 'X-XSRF-TOKEN') {
            $header = $request->getHeaderLine('X-XSRF-TOKEN');
            if ($header !== '') {
                return $header;
            }
        }

        if ($this->headerKey !== 'X-CSRF-Token') {
            $header = $request->getHeaderLine('X-CSRF-Token');
            if ($header !== '') {
                return $header;
            }
        }

        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[$this->inputKey]) && is_string($body[$this->inputKey])) {
            return $body[$this->inputKey];
        }

        return null;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
