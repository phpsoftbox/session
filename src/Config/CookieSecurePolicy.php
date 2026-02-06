<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Config;

use Psr\Http\Message\ServerRequestInterface;

use function strtolower;

enum CookieSecurePolicy: string
{
    case Always = 'always';
    case Never  = 'never';
    case Auto   = 'auto';

    public static function fromBoolean(bool $secure): self
    {
        return $secure ? self::Always : self::Never;
    }

    public function resolve(?ServerRequestInterface $request = null): bool
    {
        return match ($this) {
            self::Always => true,
            self::Never  => false,
            self::Auto   => $request === null || strtolower($request->getUri()->getScheme()) === 'https',
        };
    }
}
