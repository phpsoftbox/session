<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

use PhpSoftBox\Cookie\SameSite;

final readonly class SessionConfig
{
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
    ) {
    }
}
