<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests\Fixtures;

use PhpSoftBox\Session\SessionStoreInterface;

final class SessionStoreSpy implements SessionStoreInterface
{
    public bool $started = false;
    public int $writes   = 0;
    public array $data   = [];

    public function start(): void
    {
        $this->started = true;
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
        $this->writes++;
        $this->data = $data;
    }

    public function regenerateId(bool $deleteOldSession = true): void
    {
    }

    public function destroy(): void
    {
        $this->data    = [];
        $this->started = false;
    }
}
