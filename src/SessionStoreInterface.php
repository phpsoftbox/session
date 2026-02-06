<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

interface SessionStoreInterface
{
    public function start(): void;
    public function isStarted(): bool;
    public function read(): array;
    public function write(array $data): void;
    public function regenerateId(bool $deleteOldSession = true): void;
    public function destroy(): void;
}
