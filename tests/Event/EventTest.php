<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Event;


use PHPUnit\Framework\TestCase;
use Xycc\Winter\Event\EventDispatcher;
use Xycc\Winter\Tests\Event\Events\AEvent;
use Xycc\Winter\Tests\Event\Events\AsyncEvent;
use Xycc\Winter\Tests\Event\Events\BEvent;
use Xycc\Winter\Tests\Event\Events\StopEvent;
use Xycc\Winter\Tests\Event\Listeners\AListener;
use Xycc\Winter\Tests\Event\Listeners\BListener;
use Xycc\Winter\Tests\Event\Listeners\StopListener;

/**
 * @author xycc
 */
class EventTest extends TestCase
{
    /**
     * @depends testAddDispatcher
     */
    public function testDispatch(EventDispatcher $dispatcher)
    {
        $this->expectOutputString('xxx');
        $dispatcher->dispatch(new AEvent('xxx'));
    }

    //public function testAddDispatcher(): EventDispatcher
    //{
    //    $dispatcher = new EventDispatcher();
    //    $dispatcher->addListeners(AEvent::class, [AListener::class]);
    //    $dispatcher->addListeners(AsyncEvent::class, [AListener::class]);
    //    $dispatcher->addListeners(StopEvent::class, [AListener::class]);
    //    $dispatcher->addListeners(BEvent::class, [AListener::class]);
    //
    //    $listeners = $dispatcher->getListenersForEvent(new AEvent('xx'));
    //    $this->assertEquals(['async' => false, 'listeners' => [AListener::class]], $listeners);
    //    return $dispatcher;
    //}

    /**
     * @depends testAddDispatcher
     */
    public function testPropagation(EventDispatcher $dispatcher)
    {
        $dispatcher->addListeners(StopEvent::class, [StopListener::class, BListener::class]);
        $this->expectOutputString('stop');
        $this->expectOutputString('stop'.StopEvent::class);

        $dispatcher->dispatch(new StopEvent('stop'));
    }
}