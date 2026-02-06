<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

use Psr\Http\Message\ServerRequestInterface;

interface RequestAwareSessionInterface extends SessionInterface
{
    public function startFor(ServerRequestInterface $request): void;
}
