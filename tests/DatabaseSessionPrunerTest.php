<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use DateTimeImmutable;
use PhpSoftBox\Database\Configurator\DatabaseFactory;
use PhpSoftBox\Database\Connection\ConnectionManager;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;
use PhpSoftBox\Session\Maintenance\DatabaseSessionPruner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(DatabaseSessionPruner::class)]
final class DatabaseSessionPrunerTest extends TestCase
{
    /**
     * Проверяет, что удаляются сессии старше указанной даты последней активности.
     */
    #[Test]
    public function removesSessionsOlderThanBeforeDate(): void
    {
        $manager = $this->connectionManager();
        $this->createSessionsTable($manager);

        $this->insertSession($manager, 'active', '2026-07-14 10:00:00', '2026-07-14 09:00:00');
        $this->insertSession($manager, 'old', '2026-07-14 08:00:00', '2026-07-14 07:00:00');
        $this->insertSession($manager, 'created-old', null, '2026-07-14 07:30:00');

        $result = new DatabaseSessionPruner($manager)->prune(
            before: new DateTimeImmutable('2026-07-14 09:00:00'),
        );

        self::assertSame(2, $result->deleted);
        self::assertSame(['active'], $this->sessionIds($manager));
    }

    /**
     * Проверяет, что dry-run считает записи, но не удаляет их.
     */
    #[Test]
    public function dryRunCountsOldSessionsWithoutDeletingThem(): void
    {
        $manager = $this->connectionManager();
        $this->createSessionsTable($manager);

        $this->insertSession($manager, 'active', '2026-07-14 10:00:00', '2026-07-14 09:00:00');
        $this->insertSession($manager, 'old', '2026-07-14 08:00:00', '2026-07-14 07:00:00');

        $result = new DatabaseSessionPruner($manager)->prune(
            before: new DateTimeImmutable('2026-07-14 09:00:00'),
            dryRun: true,
        );

        self::assertTrue($result->dryRun);
        self::assertSame(1, $result->matched);
        self::assertSame(0, $result->deleted);
        self::assertSame(['active', 'old'], $this->sessionIds($manager));
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

    private function insertSession(
        ConnectionManager $manager,
        string $sessionId,
        ?string $lastActivityDatetime,
        string $createdDatetime,
    ): void {
        $manager->connection()->query()
            ->insert('sessions', [
                'session_id'             => $sessionId,
                'guard'                  => 'guest',
                'payload'                => 'a:0:{}',
                'last_activity_datetime' => $lastActivityDatetime,
                'created_datetime'       => $createdDatetime,
                'updated_datetime'       => $createdDatetime,
            ])
            ->execute();
    }

    /**
     * @return list<string>
     */
    private function sessionIds(ConnectionManager $manager): array
    {
        $rows = $manager->connection()->fetchAll('SELECT session_id FROM sessions ORDER BY session_id ASC');

        return array_map(static fn (array $row): string => (string) $row['session_id'], $rows);
    }
}
