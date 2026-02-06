<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionInterface $session,
        private readonly string $attribute = 'session',
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->session->start();

        $request = $request->withAttribute($this->attribute, $this->session);

        try {
            return $handler->handle($request);
        } finally {
            $this->session->save();
        }
    }
}
