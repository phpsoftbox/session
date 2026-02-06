<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Cli;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PhpSoftBox\CliApp\Command\HandlerInterface;
use PhpSoftBox\CliApp\Response;
use PhpSoftBox\CliApp\Runner\RunnerInterface;
use PhpSoftBox\Session\Config\SessionConfig;
use PhpSoftBox\Session\Maintenance\DatabaseSessionPruner;

use function is_int;
use function is_string;
use function trim;

final readonly class SessionPruneHandler implements HandlerInterface
{
    public function __construct(
        private DatabaseSessionPruner $pruner,
        private ?SessionConfig $config = null,
    ) {
    }

    public function run(RunnerInterface $runner): int|Response
    {
        $maxLifetime = $this->positiveInt(
            $runner->request()->option('max-lifetime', null),
            $this->config?->gcMaxLifetime ?? 1440,
        );
        if ($maxLifetime === null) {
            $runner->io()->writeln('Опция --max-lifetime должна быть целым числом больше нуля.', 'error');

            return Response::INVALID_INPUT;
        }

        $connection = $this->stringOption($runner->request()->option('connection', 'default'), 'default');
        $table      = $this->stringOption($runner->request()->option('table', 'sessions'), 'sessions');
        $lastColumn = $this->stringOption(
            $runner->request()->option('last-activity-column', 'last_activity_datetime'),
            'last_activity_datetime',
        );
        $createdColumn = $this->stringOption(
            $runner->request()->option('created-column', 'created_datetime'),
            'created_datetime',
        );

        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'))
            ->sub(new DateInterval('PT' . $maxLifetime . 'S'));

        try {
            $result = $this->pruner->prune(
                before: $before,
                connectionName: $connection,
                table: $table,
                lastActivityDatetimeColumn: $lastColumn,
                createdDatetimeColumn: $createdColumn,
                dryRun: (bool) $runner->request()->option('dry-run', false),
            );
        } catch (InvalidArgumentException $exception) {
            $runner->io()->writeln($exception->getMessage(), 'error');

            return Response::INVALID_INPUT;
        }

        $runner->io()->table(
            ['Метрика', 'Значение'],
            [
                ['Старше', $result->before->format('Y-m-d H:i:s')],
                ['Найдено', $result->matched],
                ['Удалено', $result->deleted],
                ['Dry run', $result->dryRun ? 'yes' : 'no'],
            ],
        );

        $runner->io()->writeln('Очистка DB-сессий завершена.', 'success');

        return Response::SUCCESS;
    }

    private function positiveInt(mixed $value, int $default): ?int
    {
        if ($value === null || $value === '') {
            return $default > 0 ? $default : null;
        }

        if (!is_int($value)) {
            return null;
        }

        return $value > 0 ? $value : null;
    }

    private function stringOption(mixed $value, string $default): string
    {
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }
}
