<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Exception\CsrfTokenMismatchException;
use PhpSoftBox\Session\Http\CsrfMiddleware;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Tests\Fixtures\SessionStoreSpy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(CsrfMiddleware::class)]
#[CoversMethod(CsrfMiddleware::class, 'process')]
final class CsrfMiddlewareTest extends TestCase
{
    /**
     * Проверяем генерацию токена и его наличие в атрибуте.
     *
     * @see CsrfMiddleware::process()
     */
    #[Test]
    public function testGeneratesToken(): void
    {
        $session = new Session(new SessionStoreSpy());

        $middleware = new CsrfMiddleware($session);

        $request = new ServerRequest('GET', 'https://example.com/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200, ['X-Token' => (string) $request->getAttribute('csrf_token')]);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertNotSame('', $response->getHeaderLine('X-Token'));
    }

    /**
     * Проверяем, что при неверном токене выбрасывается исключение.
     *
     * @see CsrfMiddleware::process()
     */
    #[Test]
    public function testInvalidTokenThrows(): void
    {
        $session = new Session(new SessionStoreSpy());

        $middleware = new CsrfMiddleware($session);

        $request = (new ServerRequest('POST', 'https://example.com/', parsedBody: ['_token' => 'bad']));

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $this->expectException(CsrfTokenMismatchException::class);
        $middleware->process($request, $handler);
    }

    /**
     * Проверяем, что CSRF токен помещается в очередь cookie.
     *
     * @see CookieQueue::flush()
     * @see CsrfMiddleware::process()
     */
    #[Test]
    public function testQueuesXsrfCookie(): void
    {
        $session = new Session(new SessionStoreSpy());

        $middleware = new CsrfMiddleware($session);

        $queue = new CookieQueue();

        $request = new ServerRequest('GET', 'https://example.com/')
                    ->withAttribute('cookie_queue', $queue);

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $cookies = $queue->flush();
        $this->assertCount(1, $cookies);
        $this->assertStringContainsString('XSRF-TOKEN=', $cookies[0]->toHeader());
    }
}
