<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Store;

use DateTimeImmutable;
use DateTimeZone;
use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Cookie\SetCookie;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;
use PhpSoftBox\Session\Config\SessionConfig;
use Psr\Http\Message\ServerRequestInterface;

use function bin2hex;
use function is_array;
use function is_int;
use function is_string;
use function random_bytes;
use function serialize;
use function sprintf;
use function trim;
use function unserialize;

final class DatabaseSessionStore implements RequestAwareSessionStoreInterface
{
    private bool $started                    = false;
    private ?ServerRequestInterface $request = null;
    private ?string $sessionId               = null;

    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param bool|list<class-string> $allowedPayloadClasses
     * @param array<string, string> $userIdKeys Session key map by guard name.
     */
    public function __construct(
        private readonly ConnectionManagerInterface $connections,
        private readonly CookieQueue $cookies,
        private readonly SessionConfig $config = new SessionConfig(),
        private readonly string $connectionName = 'default',
        private readonly string $table = 'sessions',
        private readonly string $idColumn = 'session_id',
        private readonly string $payloadColumn = 'payload',
        private readonly string $userIdColumn = 'user_id',
        private readonly string $userIdKey = 'auth.user_id',
        private readonly string $ipAddressColumn = 'ip_address',
        private readonly string $userAgentColumn = 'user_agent',
        private readonly string $lastActivityDatetimeColumn = 'last_activity_datetime',
        private readonly string $createdDatetimeColumn = 'created_datetime',
        private readonly string $updatedDatetimeColumn = 'updated_datetime',
        private readonly bool|array $allowedPayloadClasses = false,
        private readonly string $guardColumn = 'guard',
        private readonly string $guestGuard = 'guest',
        private readonly array $userIdKeys = [],
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->sessionId = $this->resolveSessionId();
        $row             = $this->findRow($this->sessionId);
        $this->data      = $row === null ? [] : $this->decodePayload($row[$this->payloadColumn] ?? null);
        $this->started   = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function read(): array
    {
        return $this->data;
    }

    public function write(array $data): void
    {
        if (!$this->started) {
            return;
        }

        $this->data = $data;
        $this->persist($data);
        $this->queueCookie();

        $this->started = false;
        $this->request = null;
    }

    public function regenerateId(bool $deleteOldSession = true): void
    {
        $oldSessionId    = $this->sessionId;
        $this->sessionId = $this->generateSessionId();

        if ($deleteOldSession && $oldSessionId !== null) {
            $this->deleteSession($oldSessionId);
        }
    }

    public function destroy(): void
    {
        if ($this->sessionId !== null) {
            $this->deleteSession($this->sessionId);
        }

        $this->data      = [];
        $this->started   = false;
        $this->sessionId = null;
        $this->cookies->queue($this->forgetCookie());
        $this->request = null;
    }

    private function resolveSessionId(): string
    {
        if ($this->sessionId !== null) {
            return $this->sessionId;
        }

        $cookies = $this->request?->getCookieParams() ?? [];
        $value   = $cookies[$this->config->name] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $this->generateSessionId();
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(string $sessionId): ?array
    {
        $conn = $this->connections->read($this->connectionName);
        $sql  = sprintf(
            'SELECT * FROM %s WHERE %s = :session_id LIMIT 1',
            $conn->table($this->table),
            $this->idColumn,
        );

        return $conn->fetchOne($sql, ['session_id' => $sessionId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $data = @unserialize($payload, [
            'allowed_classes' => $this->allowedPayloadClasses,
        ]);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persist(array $data): void
    {
        $sessionId       = $this->sessionId ?? $this->generateSessionId();
        $this->sessionId = $sessionId;

        $now      = $this->dateToStorage(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $identity = $this->identity($data);
        $row      = [
            $this->payloadColumn              => serialize($data),
            $this->guardColumn                => $identity['guard'],
            $this->userIdColumn               => $identity['user_id'],
            $this->ipAddressColumn            => $this->requestIp(),
            $this->userAgentColumn            => $this->requestUserAgent(),
            $this->lastActivityDatetimeColumn => $now,
            $this->updatedDatetimeColumn      => $now,
        ];

        $conn = $this->connections->write($this->connectionName);
        if ($this->findRow($sessionId) === null) {
            $conn->query()
                ->insert($this->table, [
                    $this->idColumn              => $sessionId,
                    $this->createdDatetimeColumn => $now,
                    ...$row,
                ])
                ->execute();

            return;
        }

        $conn->query()
            ->update($this->table, $row)
            ->where($this->idColumn . ' = :session_id', ['session_id' => $sessionId])
            ->execute();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{guard: string, user_id: int|string|null}
     */
    private function identity(array $data): array
    {
        foreach ($this->identityKeys() as $guard => $sessionKey) {
            $value = $data[$sessionKey] ?? null;
            if (is_string($value) || is_int($value)) {
                return [
                    'guard'   => $guard,
                    'user_id' => $value,
                ];
            }
        }

        return [
            'guard'   => $this->normalizedGuestGuard(),
            'user_id' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function identityKeys(): array
    {
        $keys = [];
        foreach ($this->userIdKeys as $guard => $sessionKey) {
            if (!is_string($guard) || !is_string($sessionKey)) {
                continue;
            }

            $guard      = trim($guard);
            $sessionKey = trim($sessionKey);
            if ($guard === '' || $sessionKey === '') {
                continue;
            }

            $keys[$guard] = $sessionKey;
        }

        if ($keys !== []) {
            return $keys;
        }

        return ['web' => $this->userIdKey];
    }

    private function normalizedGuestGuard(): string
    {
        $guard = trim($this->guestGuard);

        return $guard !== '' ? $guard : 'guest';
    }

    private function deleteSession(string $sessionId): void
    {
        $this->connections->write($this->connectionName)
            ->query()
            ->delete($this->table)
            ->where($this->idColumn . ' = :session_id', ['session_id' => $sessionId])
            ->execute();
    }

    private function queueCookie(): void
    {
        if ($this->sessionId === null) {
            return;
        }

        $cookie = SetCookie::create($this->config->name, $this->sessionId)
            ->withPath($this->config->path)
            ->withDomain($this->config->domain)
            ->withSecure($this->config->secureFor($this->request))
            ->withHttpOnly($this->config->httpOnly)
            ->withSameSite($this->config->sameSite);

        if ($this->config->lifetime > 0) {
            $cookie = $cookie->withMaxAge($this->config->lifetime);
        }

        $this->cookies->queue($cookie);
    }

    private function forgetCookie(): SetCookie
    {
        return SetCookie::create($this->config->name, '')
            ->withExpires(1)
            ->withMaxAge(0)
            ->withPath($this->config->path)
            ->withDomain($this->config->domain)
            ->withSecure($this->config->secureFor($this->request))
            ->withHttpOnly($this->config->httpOnly)
            ->withSameSite($this->config->sameSite);
    }

    private function requestIp(): ?string
    {
        $server = $this->request?->getServerParams() ?? [];
        $value  = $server['REMOTE_ADDR'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function requestUserAgent(): ?string
    {
        $value = $this->request?->getHeaderLine('User-Agent') ?? '';

        return trim($value) !== '' ? trim($value) : null;
    }

    private function dateToStorage(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
