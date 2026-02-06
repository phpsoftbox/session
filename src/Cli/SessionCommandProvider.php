<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Cli;

use PhpSoftBox\CliApp\Command\Command;
use PhpSoftBox\CliApp\Command\CommandRegistryInterface;
use PhpSoftBox\CliApp\Command\OptionDefinition;
use PhpSoftBox\CliApp\Loader\CommandProviderInterface;

final class SessionCommandProvider implements CommandProviderInterface
{
    public function register(CommandRegistryInterface $registry): void
    {
        $registry->register(Command::define(
            name: 'session:prune',
            description: 'Удалить устаревшие DB-сессии',
            signature: [
                new OptionDefinition(
                    name: 'connection',
                    short: 'c',
                    description: 'Имя database connection',
                    required: false,
                    default: 'default',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'table',
                    short: 't',
                    description: 'Имя таблицы сессий',
                    required: false,
                    default: 'sessions',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'max-lifetime',
                    short: 'l',
                    description: 'Удалить сессии без активности дольше N секунд',
                    required: false,
                    default: null,
                    type: 'int',
                ),
                new OptionDefinition(
                    name: 'last-activity-column',
                    short: null,
                    description: 'Колонка последней активности',
                    required: false,
                    default: 'last_activity_datetime',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'created-column',
                    short: null,
                    description: 'Колонка создания сессии для fallback',
                    required: false,
                    default: 'created_datetime',
                    type: 'string',
                ),
                new OptionDefinition(
                    name: 'dry-run',
                    short: 'd',
                    description: 'Показать количество записей без удаления',
                    flag: true,
                ),
            ],
            handler: SessionPruneHandler::class,
        ));
    }
}
