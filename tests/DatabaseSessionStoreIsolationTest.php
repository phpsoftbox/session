<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Http\Message\ServerRequest;
use PhpSoftBox\Session\Config\SessionConfig;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\DatabaseSessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function gmdate;
use function str_repeat;
use function time;

#[CoversClass(DatabaseSessionStore::class)]
#[CoversMethod(DatabaseSessionStore::class, 'start')]
#[CoversMethod(DatabaseSessionStore::class, 'write')]
final class DatabaseSessionStoreIsolationTest extends TestCase
{
    /**
     * Проверим, что после `write()` тот же объект хранилища (синглтон в воркере) не отдаёт сессию прошлого пользователя
     * запросу без cookie.
     *
     * @see DatabaseSessionStore::write()
     */
    #[Test]
    public function resetsStateAfterWrite(): void
    {
        $manager = $this->connectionManager();
        $queue   = new CookieQueue();

        $session = new Session(new DatabaseSessionStore($manager, $queue, new SessionConfig(secure: false)));

        // Первый запрос — пользователь 10.
        $session->startFor(new ServerRequest('GET', 'http://example.test/'));
        $session->set('auth.user_id', 10);
        $session->save();
        $firstId = $queue->flush()[0]->value();

        // Второй запрос без cookie — новая пустая сессия.
        $session->startFor(new ServerRequest('GET', 'http://example.test/'));
        self::assertNull($session->get('auth.user_id'));
        $session->save();

        self::assertNotSame($firstId, $queue->flush()[0]->value());
    }

    /**
     * Проверим, что заранее известный, но отсутствующий в таблице id не становится идентификатором сессии.
     *
     * @see DatabaseSessionStore::start()
     */
    #[Test]
    public function replacesUnknownSessionId(): void
    {
        $manager = $this->connectionManager();
        $queue   = new CookieQueue();

        $session   = new Session(new DatabaseSessionStore($manager, $queue, new SessionConfig(secure: false)));
        $attackers = str_repeat('ab', 32);

        $session->startFor(new ServerRequest('GET', 'http://example.test/', cookieParams: ['psb_session' => $attackers]));
        $session->save();

        self::assertNotSame($attackers, $queue->flush()[0]->value());
    }

    /**
     * Проверим, что id не в формате генератора (например, длиннее колонки) даёт новую сессию, а не ошибку БД.
     *
     * @see DatabaseSessionStore::start()
     */
    #[Test]
    public function replacesMalformedSessionId(): void
    {
        $manager = $this->connectionManager();
        $queue   = new CookieQueue();

        $session = new Session(new DatabaseSessionStore($manager, $queue, new SessionConfig(secure: false)));

        $session->startFor(new ServerRequest('GET', 'http://example.test/', cookieParams: ['psb_session' => str_repeat('x', 300)]));
        $session->save();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $queue->flush()[0]->value());
    }

    /**
     * Проверим, что сессия, неактивная дольше `gcMaxLifetime`, не читается и удаляется, не дожидаясь `session:prune`.
     *
     * @see DatabaseSessionStore::start()
     */
    #[Test]
    public function ignoresExpiredSession(): void
    {
        $manager = $this->connectionManager();
        $config  = new SessionConfig(secure: false, gcMaxLifetime: 600);
        $queue   = new CookieQueue();

        $session = new Session(new DatabaseSessionStore($manager, $queue, $config));

        $session->startFor(new ServerRequest('GET', 'http://example.test/'));
        $session->set('auth.user_id', 10);
        $session->save();
        $sessionId = $queue->flush()[0]->value();

        // Последняя активность — час назад.
        $manager->connection()->execute(
            'UPDATE sessions SET last_activity_datetime = :at WHERE session_id = :id',
            ['at' => gmdate('Y-m-d H:i:s', time() - 3600), 'id' => $sessionId],
        );

        $session->startFor(new ServerRequest('GET', 'http://example.test/', cookieParams: ['psb_session' => $sessionId]));

        self::assertNull($session->get('auth.user_id'));
        self::assertNull($manager->connection()->fetchOne('SELECT * FROM sessions WHERE session_id = :id', ['id' => $sessionId]));
    }

    private function connectionManager(): ConnectionManager
    {
        $manager = new ConnectionManager(new DatabaseFactory([
            'connections' => [
                'default' => 'main',
                'main'    => ['dsn' => 'sqlite:///:memory:'],
            ],
        ]));

        $manager->connection()->schema()->create('sessions', static function (TableBlueprint $table): void {
            $table->id();
            $table->string('session_id', 128);
            $table->string('guard', 64)->default('guest');
            $table->string('user_id', 64)->nullable();
            $table->text('payload');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->datetime('last_activity_datetime')->nullable();
            $table->datetime('created_datetime')->nullable();
            $table->datetime('updated_datetime')->nullable();
            $table->unique(['session_id']);
        });

        return $manager;
    }
}
