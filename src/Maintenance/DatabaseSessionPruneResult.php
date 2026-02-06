<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Maintenance;

use DateTimeImmutable;

final readonly class DatabaseSessionPruneResult
{
    public function __construct(
        public DateTimeImmutable $before,
        public int $matched,
        public int $deleted,
        public bool $dryRun = false,
    ) {
    }
}
