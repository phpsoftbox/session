<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Config\CookieSecurePolicy;
use PhpSoftBox\Session\Config\SessionConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionConfig::class)]
#[CoversMethod(SessionConfig::class, 'secureFor')]
#[CoversClass(CookieSecurePolicy::class)]
#[CoversMethod(CookieSecurePolicy::class, 'resolve')]
final class SessionConfigTest extends TestCase
{
    /**
     * Проверяем, что сессия по умолчанию использует secure cookie.
     *
     * @see SessionConfig::secureFor()
     */
    #[Test]
    public function testDefaultSessionCookieIsSecure(): void
    {
        $config = new SessionConfig();

        $this->assertTrue($config->secureFor());
    }

    /**
     * Проверяем, что auto policy отключает Secure только для HTTP request.
     *
     * @see SessionConfig::secureFor()
     * @see CookieSecurePolicy::resolve()
     */
    #[Test]
    public function testAutoSecurePolicyUsesRequestScheme(): void
    {
        $config = new SessionConfig(securePolicy: CookieSecurePolicy::Auto);

        $this->assertTrue($config->secureFor(new ServerRequest('GET', 'https://example.com/')));
        $this->assertFalse($config->secureFor(new ServerRequest('GET', 'http://example.com/')));
    }
}
