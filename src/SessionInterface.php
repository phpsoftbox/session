<?php

declare(strict_types=1);

namespace PhpSoftBox\Session;

interface SessionInterface
{
    /**
     * Запускает сессию, если она ещё не запущена.
     */
    public function start(): void;

    /**
     * Проверяет, запущена ли сессия.
     */
    public function isStarted(): bool;

    /**
     * Возвращает все данные сессии.
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Возвращает значение по ключу или значение по умолчанию.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Устанавливает значение по ключу.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Проверяет наличие ключа в сессии.
     */
    public function has(string $key): bool;

    /**
     * Удаляет значение по ключу.
     */
    public function forget(string $key): void;

    /**
     * Очищает все данные сессии.
     */
    public function clear(): void;

    /**
     * Сохраняет значение во flash на следующий запрос.
     */
    public function flash(string $key, mixed $value): void;

    /**
     * Возвращает flash-значение по ключу, не удаляя его.
     */
    public function getFlash(string $key, mixed $default = null): mixed;

    /**
     * Возвращает значение по ключу и удаляет его из сессии.
     */
    public function pull(string $key, mixed $default = null): mixed;

    /**
     * Сохраняет данные сессии.
     */
    public function save(): void;

    /**
     * Регенерирует идентификатор сессии.
     */
    public function regenerate(bool $deleteOldSession = true): void;

    /**
     * Завершает сессию и удаляет данные.
     */
    public function destroy(): void;
}
