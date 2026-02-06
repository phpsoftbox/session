# Session

Компонент для работы с сессиями.

## Структура

- `PhpSoftBox\Session` — session API.
- `PhpSoftBox\Session\Config` — настройки session и cookie.
- `PhpSoftBox\Session\Store` — драйверы хранения session.
- `PhpSoftBox\Session\Http` — HTTP middleware.
- `PhpSoftBox\Session\Maintenance` — сервисные операции над хранилищем session.
- `PhpSoftBox\Session\Cli` — CLI-команды компонента.

## Пример

```php
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\NativeSessionStore;

$session = new Session(new NativeSessionStore());
$session->start();

$session->set('user_id', 1);
$session->flash('notice', 'Saved');

$session->save();
```

## DatabaseSessionStore

Если session нужно хранить в БД, используйте `DatabaseSessionStore`.
Он читает session id из cookie, хранит payload в таблице `sessions` и обновляет
cookie через общий `CookieQueue`.

```php
use PhpSoftBox\Cookie\CookieQueue;
use PhpSoftBox\Session\Config\SessionConfig;
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\DatabaseSessionStore;

$store = new DatabaseSessionStore(
    connections: $connections,
    cookies: new CookieQueue(),
    config: new SessionConfig(name: 'psb_session'),
    userIdKeys: [
        'web' => 'auth.user_id',
        'tenant' => 'tenant.auth.user_id',
    ],
);

$session = new Session($store);
```

`userIdKeys` связывает auth guard с ключом в payload сессии. Если ни один ключ
не найден, store записывает `guard=guest` и `user_id=NULL`.

Пример миграции лежит в `database/migrations`. В приложении её можно
опубликовать через `db:migrate:publish --package=phpsoftbox/session`.

## Очистка DB-сессий

`DatabaseSessionStore` не запускает PHP session GC автоматически. Для удаления
старых записей используйте CLI-команду:

```bash
php psb session:prune --max-lifetime 86400
```

Сессия считается старой, если `last_activity_datetime` меньше текущего времени
минус `max-lifetime` секунд. Если `last_activity_datetime` пустой, команда
проверяет `created_datetime`. Без `--max-lifetime` используется
`SessionConfig::$gcMaxLifetime`, а если он не задан — `1440` секунд.

Для проверки без удаления:

```bash
php psb session:prune --dry-run
```

## Middleware

```php
use PhpSoftBox\Session\Http\CsrfMiddleware;
use PhpSoftBox\Session\Http\SessionMiddleware;

$sessionMw = new SessionMiddleware($session);
$csrfMw = new CsrfMiddleware($session);
```
