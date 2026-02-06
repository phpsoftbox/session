# Session

Компонент для работы с сессиями.

## Пример

```php
use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\NativeSessionStore;

$session = new Session(new NativeSessionStore());
$session->start();

$session->set('user_id', 1);
$session->flash('notice', 'Saved');

$session->save();
```

## Middleware

```php
use PhpSoftBox\Session\CsrfMiddleware;
use PhpSoftBox\Session\SessionMiddleware;

$sessionMw = new SessionMiddleware($session);
$csrfMw = new CsrfMiddleware($session);
```
