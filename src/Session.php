<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

use function array_diff;
use function array_key_exists;
use function is_array;

final class Session implements SessionInterface
{
    private bool $started = false;

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly SessionStoreInterface $store,
    ) {
    }

    public function start(): void
    {
        if ($this->store->isStarted()) {
            if (!$this->started) {
                $this->data = $this->store->read();
                $this->ageFlashData();
                $this->started = true;
            }

            return;
        }

        $this->store->start();
        $this->data = $this->store->read();
        if (!$this->started) {
            $this->ageFlashData();
        }
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started || $this->store->isStarted();
    }

    public function all(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);

        $this->forgetFlashKey($key);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function flash(string $key, mixed $value): void
    {
        $this->initializeFlash();

        $this->data[$key]              = $value;
        $this->data['_flash']['new'][] = $key;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    public function save(): void
    {
        if (!$this->started) {
            return;
        }

        $this->store->write($this->data);
        $this->started = $this->store->isStarted();
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->store->regenerateId($deleteOldSession);
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->store->destroy();
        $this->started = false;
    }

    private function initializeFlash(): void
    {
        if (!isset($this->data['_flash']) || !is_array($this->data['_flash'])) {
            $this->data['_flash'] = [
                'new' => [],
                'old' => [],
            ];

            return;
        }

        if (!isset($this->data['_flash']['new'])) {
            $this->data['_flash']['new'] = [];
        }
        if (!isset($this->data['_flash']['old'])) {
            $this->data['_flash']['old'] = [];
        }
    }

    private function ageFlashData(): void
    {
        $this->initializeFlash();

        foreach ($this->data['_flash']['old'] as $key) {
            unset($this->data[$key]);
        }

        $this->data['_flash']['old'] = $this->data['_flash']['new'];
        $this->data['_flash']['new'] = [];
    }

    private function forgetFlashKey(string $key): void
    {
        if (!isset($this->data['_flash']) || !is_array($this->data['_flash'])) {
            return;
        }

        $this->data['_flash']['new'] = array_diff($this->data['_flash']['new'] ?? [], [$key]);
        $this->data['_flash']['old'] = array_diff($this->data['_flash']['old'] ?? [], [$key]);
    }
}
