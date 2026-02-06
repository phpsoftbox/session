<?php

declare(strict_types=1);

namespace PhpSoftBox\Session\Tests;

use PhpSoftBox\Session\Session;
use PhpSoftBox\Session\Store\ArraySessionStore;
use PhpSoftBox\Session\Tests\Fixtures\CloseOnWriteStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
#[CoversMethod(Session::class, 'start')]
#[CoversMethod(Session::class, 'set')]
#[CoversMethod(Session::class, 'get')]
#[CoversMethod(Session::class, 'has')]
#[CoversMethod(Session::class, 'forget')]
#[CoversMethod(Session::class, 'flash')]
#[CoversMethod(Session::class, 'getFlash')]
#[CoversMethod(Session::class, 'save')]
#[CoversMethod(Session::class, 'pull')]
#[CoversMethod(Session::class, 'destroy')]
#[CoversMethod(Session::class, 'all')]
final class SessionTest extends TestCase
{
    /**
     * Проверяем базовые операции set/get/has/forget.
     *
     * @see Session::start()
     * @see Session::set()
     * @see Session::get()
     * @see Session::has()
     * @see Session::forget()
     */
    #[Test]
    public function testBasicOperations(): void
    {
        $session = new Session(new ArraySessionStore());

        $session->start();

        $session->set('a', 1);

        $this->assertTrue($session->has('a'));
        $this->assertSame(1, $session->get('a'));

        $session->forget('a');

        $this->assertFalse($session->has('a'));
    }

    /**
     * Проверяем flash-данные.
     *
     * @see Session::flash()
     * @see Session::getFlash()
     * @see Session::save()
     * @see Session::start()
     */
    #[Test]
    public function testFlashData(): void
    {
        $store = new ArraySessionStore();

        $session = new Session($store);

        $session->start();

        $session->flash('notice', 'ok');
        $this->assertSame('ok', $session->getFlash('notice'));

        $session->save();

        $session2 = new Session($store);

        $session2->start();

        $this->assertSame('ok', $session2->getFlash('notice'));

        $session2->save();

        $session3 = new Session($store);

        $session3->start();

        $this->assertNull($session3->getFlash('notice'));
    }

    /**
     * Проверяем повторный запуск сессии после сохранения.
     *
     * @see Session::start()
     * @see Session::set()
     * @see Session::flash()
     * @see Session::save()
     */
    #[Test]
    public function testRestartAfterSave(): void
    {
        $store = new CloseOnWriteStore();

        $session = new Session($store);

        $session->start();
        $session->set('foo', 'bar');
        $session->save();

        $session->start();
        $session->flash('notice', 'ok');
        $session->save();

        $freshSession = new Session($store);

        $freshSession->start();

        $this->assertSame('ok', $freshSession->getFlash('notice'));
    }

    /**
     * Проверяем pull() и destroy().
     *
     * @see Session::all()
     * @see Session::destroy()
     * @see Session::get()
     * @see Session::pull()
     * @see Session::set()
     */
    #[Test]
    public function testPullAndDestroy(): void
    {
        $store = new ArraySessionStore();

        $session = new Session($store);

        $session->start();

        $session->set('key', 'value');

        $this->assertSame('value', $session->pull('key'));
        $this->assertNull($session->get('key'));

        $session->set('a', 1);
        $session->destroy();

        $this->assertSame([], $session->all());
    }
}
