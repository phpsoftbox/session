<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use PhpSoftBox\Http\Message\Response;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\SessionMiddleware;
use PhpSoftBox\Session\Tests\Fixtures\SessionStoreSpy;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddlewareTest extends TestCase
{
    /**
     * Проверяем, что сессия стартует и сохраняется.
     */
    public function testSessionStartAndSave(): void
    {
        $store = new SessionStoreSpy();

        $session = new Session($store);

        $middleware = new SessionMiddleware($session);
        $request    = new ServerRequest('GET', 'https://example.com/');

        $handler = new class () implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request,
            ): ResponseInterface {
                return new Response(200);
            }
        };

        $middleware->process($request, $handler);

        $this->assertTrue($store->started);
        $this->assertSame(1, $store->writes);
    }
}
