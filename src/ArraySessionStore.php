<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

final class ArraySessionStore implements SessionStoreInterface
{
    private bool $started = false;
    private array $data   = [];

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
