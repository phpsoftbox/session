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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseSessionStore::class)]
final class DatabaseSessionStoreTest extends TestCase
{
    /**
     * Проверяет, что сессия сохраняется в БД и повторно читается по cookie.
     */
    #[Test]
    public function persistsAndReadsSessionByCookie(): void
    {
        $manager = $this->connectionManager();
        $this->createSessionsTable($manager);

        $queue = new CookieQueue();

        $session = new Session(new DatabaseSessionStore(
            connections: $manager,
            cookies: $queue,
            config: new SessionConfig(secure: false),
        ));

        $session->startFor(new ServerRequest('GET', 'http://example.test/', serverParams: ['REMOTE_ADDR' => '127.0.0.1']));
        $session->set('auth.user_id', 10);
        $session->set('name', 'Anton');
        $session->save();

        $cookies = $queue->flush();
        self::assertCount(1, $cookies);

        $sessionId = $cookies[0]->value();
        $row       = $manager->connection()->fetchOne('SELECT * FROM sessions WHERE session_id = :id', ['id' => $sessionId]);

        self::assertNotNull($row);
        self::assertSame('web', $row['guard']);
        self::assertSame('10', (string) $row['user_id']);
        self::assertSame('127.0.0.1', $row['ip_address']);

        $next = new Session(new DatabaseSessionStore(
            connections: $manager,
            cookies: new CookieQueue(),
            config: new SessionConfig(secure: false),
        ));

        $next->startFor(new ServerRequest('GET', 'http://example.test/', cookieParams: ['psb_session' => $sessionId]));

        self::assertSame('Anton', $next->get('name'));
        self::assertSame(10, $next->get('auth.user_id'));
    }

    /**
     * Проверяет, что store сохраняет guard по настроенному session key.
     */
    #[Test]
    public function writesGuardFromConfiguredUserIdKeys(): void
    {
        $manager = $this->connectionManager();
        $this->createSessionsTable($manager);

        $queue = new CookieQueue();

        $session = new Session(new DatabaseSessionStore(
            connections: $manager,
            cookies: $queue,
            config: new SessionConfig(secure: false),
            userIdKeys: [
                'web'    => 'auth.user_id',
                'tenant' => 'tenant.auth.user_id',
            ],
        ));

        $session->startFor(new ServerRequest('GET', 'http://tenant.example.test/'));
        $session->set('tenant.auth.user_id', 25);
        $session->save();

        $sessionId = $queue->flush()[0]->value();
        $row       = $manager->connection()->fetchOne('SELECT * FROM sessions WHERE session_id = :id', ['id' => $sessionId]);

        self::assertNotNull($row);
        self::assertSame('tenant', $row['guard']);
        self::assertSame('25', (string) $row['user_id']);
    }

    /**
     * Проверяет, что неавторизованная сессия получает guard guest.
     */
    #[Test]
    public function writesGuestGuardForAnonymousSession(): void
    {
        $manager = $this->connectionManager();
        $this->createSessionsTable($manager);

        $queue = new CookieQueue();

        $session = new Session(new DatabaseSessionStore(
            connections: $manager,
            cookies: $queue,
            config: new SessionConfig(secure: false),
            userIdKeys: [
                'web'    => 'auth.user_id',
                'tenant' => 'tenant.auth.user_id',
            ],
        ));

        $session->startFor(new ServerRequest('GET', 'http://example.test/'));
        $session->set('csrf', 'token');
        $session->save();

        $sessionId = $queue->flush()[0]->value();
        $row       = $manager->connection()->fetchOne('SELECT * FROM sessions WHERE session_id = :id', ['id' => $sessionId]);

        self::assertNotNull($row);
        self::assertSame('guest', $row['guard']);
        self::assertNull($row['user_id']);
    }

    /**
     * Проверяет, что regenerateId(true) удаляет старую DB-сессию.
     */
    #[Test]
    public function regenerateDeletesOldSession(): void
    {
        $manager = $this->connectionManager();
        $this->createSessionsTable($manager);

        $queue = new CookieQueue();

        $session = new Session(new DatabaseSessionStore(
            connections: $manager,
            cookies: $queue,
            config: new SessionConfig(secure: false),
        ));

        $session->startFor(new ServerRequest('GET', 'http://example.test/'));
        $session->set('value', 'old');
        $session->save();
        $oldId = $queue->flush()[0]->value();

        $session->startFor(new ServerRequest('GET', 'http://example.test/', cookieParams: ['psb_session' => $oldId]));
        $session->regenerate();
        $session->set('value', 'new');
        $session->save();

        self::assertNull($manager->connection()->fetchOne('SELECT * FROM sessions WHERE session_id = :id', ['id' => $oldId]));
    }

    private function connectionManager(): ConnectionManager
    {
        $factory = new DatabaseFactory([
            'connections' => [
                'default' => 'main',
                'main'    => [
                    'dsn' => 'sqlite:///:memory:',
                ],
            ],
        ]);

        return new ConnectionManager($factory);
    }

    private function createSessionsTable(ConnectionManager $manager): void
    {
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
    }
}
