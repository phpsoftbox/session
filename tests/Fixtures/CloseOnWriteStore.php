<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests\Fixtures;

use PhpSoftBox\Session\Store\SessionStoreInterface;

final class CloseOnWriteStore implements SessionStoreInterface
{
    private bool $started = false;

    /** @var array<string, mixed> */
    private array $data = [];

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
        if (!$this->started) {
            return;
        }

        $this->data    = $data;
        $this->started = false;
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
