<?php

declare(strict_types=1);

use PhpSoftBox\Database\Migrations\AbstractMigration;
use PhpSoftBox\Database\SchemaBuilder\TableBlueprint;

return new class () extends AbstractMigration {
    public function up(): void
    {
        $this->schema()->create('sessions', static function (TableBlueprint $table): void {
            $table->comment('Хранилище сессий');

            $table->id()->comment('Внутренний идентификатор записи');
            $table->string('session_id', 128)->comment('Публичный идентификатор сессии');
            $table->string('guard', 64)->default('guest')->comment('Auth guard сессии');
            $table->string('user_id', 64)->nullable()->comment('Идентификатор авторизованного пользователя');
            $table->text('payload')->comment('Сериализованные данные сессии');
            $table->string('ip_address', 45)->nullable()->comment('Сетевой адрес последнего запроса');
            $table->string('user_agent', 512)->nullable()->comment('Пользовательский агент последнего запроса');
            $table->datetime('last_activity_datetime')->nullable()->comment('Дата и время последней активности');
            $table->datetime('created_datetime')->nullable()->comment('Дата и время создания');
            $table->datetime('updated_datetime')->nullable()->comment('Дата и время обновления');

            $table->unique(['session_id'], 'sessions_session_id_unique');
            $table->index(['guard', 'user_id'], 'sessions_guard_user_id_index');
            $table->index(['user_id'], 'sessions_user_id_index');
            $table->index(['last_activity_datetime'], 'sessions_last_activity_datetime_index');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('sessions');
    }
};
