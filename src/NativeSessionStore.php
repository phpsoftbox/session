<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

use RuntimeException;

use function ini_set;
use function session_destroy;
use function session_name;
use function session_regenerate_id;
use function session_set_cookie_params;
use function session_start;
use function session_status;
use function session_write_close;

use const PHP_SESSION_ACTIVE;

final class NativeSessionStore implements SessionStoreInterface
{
    private bool $started = false;

    public function __construct(
        private readonly SessionConfig $config = new SessionConfig(),
    ) {
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        if ($this->config->useStrictMode) {
            ini_set('session.use_strict_mode', '1');
        }

        if ($this->config->useOnlyCookies) {
            ini_set('session.use_only_cookies', '1');
        }

        if (!$this->config->useCookies) {
            ini_set('session.use_cookies', '0');
        }

        if ($this->config->gcMaxLifetime !== null) {
            ini_set('session.gc_maxlifetime', (string) $this->config->gcMaxLifetime);
        }

        session_name($this->config->name);
        session_set_cookie_params([
            'lifetime' => $this->config->lifetime,
            'path'     => $this->config->path,
            'domain'   => $this->config->domain,
            'secure'   => $this->config->secure,
            'httponly' => $this->config->httpOnly,
            'samesite' => $this->config->sameSite->value,
        ]);

        if (!session_start()) {
            throw new RuntimeException('Не удалось запустить сессию.');
        }

        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }

    public function read(): array
    {
        return $_SESSION ?? [];
    }

    public function write(array $data): void
    {
        $_SESSION = $data;

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->started = false;
    }

    public function regenerateId(bool $deleteOldSession = true): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_regenerate_id($deleteOldSession);
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_destroy();
        $_SESSION      = [];
        $this->started = false;
    }
}
