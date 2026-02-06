<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Store;

use Psr\Http\Message\ServerRequestInterface;

interface RequestAwareSessionStoreInterface extends SessionStoreInterface
{
    public function setRequest(ServerRequestInterface $request): void;
}
