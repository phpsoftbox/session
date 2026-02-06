<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Maintenance;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PhpSoftBox\Database\Connection\ConnectionManagerInterface;

use function preg_match;
use function sprintf;
use function trim;

final readonly class DatabaseSessionPruner
{
    public function __construct(
        private ConnectionManagerInterface $connections,
    ) {
    }

    public function prune(
        DateTimeImmutable $before,
        string $connectionName = 'default',
        string $table = 'sessions',
        string $lastActivityDatetimeColumn = 'last_activity_datetime',
        string $createdDatetimeColumn = 'created_datetime',
        bool $dryRun = false,
    ): DatabaseSessionPruneResult {
        $table                      = $this->identifier($table, 'table');
        $lastActivityDatetimeColumn = $this->identifier($lastActivityDatetimeColumn, 'last activity column');
        $createdDatetimeColumn      = $this->identifier($createdDatetimeColumn, 'created datetime column');

        $connection = $this->connections->write($connectionName);
        $where      = sprintf(
            '(%s < :before OR (%s IS NULL AND %s IS NOT NULL AND %s < :before))',
            $lastActivityDatetimeColumn,
            $lastActivityDatetimeColumn,
            $createdDatetimeColumn,
            $createdDatetimeColumn,
        );

        $params = ['before' => $this->dateToStorage($before)];

        if ($dryRun) {
            $row = $connection->fetchOne(
                sprintf('SELECT COUNT(*) AS cnt FROM %s WHERE %s', $connection->table($table), $where),
                $params,
            );
            $matched = (int) ($row['cnt'] ?? 0);

            return new DatabaseSessionPruneResult(
                before: $before,
                matched: $matched,
                deleted: 0,
                dryRun: true,
            );
        }

        $deleted = $connection->execute(
            sprintf('DELETE FROM %s WHERE %s', $connection->table($table), $where),
            $params,
        );

        return new DatabaseSessionPruneResult(
            before: $before,
            matched: $deleted,
            deleted: $deleted,
        );
    }

    private function identifier(string $value, string $label): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid session ' . $label . '.');
        }

        return $value;
    }

    private function dateToStorage(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
