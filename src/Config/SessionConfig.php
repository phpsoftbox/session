<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Config;

use PhpSoftBox\Cookie\SameSite;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SessionConfig
{
    public CookieSecurePolicy $securePolicy;

    public function __construct(
        public string $name = 'psb_session',
        public int $lifetime = 0,
        public string $path = '/',
        public ?string $domain = null,
        public bool $secure = true,
        public bool $httpOnly = true,
        public SameSite $sameSite = SameSite::Lax,
        public bool $useStrictMode = true,
        public bool $useOnlyCookies = true,
        public bool $useCookies = true,
        public ?int $gcMaxLifetime = null,
        ?CookieSecurePolicy $securePolicy = null,
    ) {
        $this->securePolicy = $securePolicy ?? CookieSecurePolicy::fromBoolean($secure);
    }

    public function secureFor(?ServerRequestInterface $request = null): bool
    {
        return $this->securePolicy->resolve($request);
    }
}
