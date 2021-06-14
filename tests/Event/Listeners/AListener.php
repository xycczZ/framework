<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Event\Listeners;


use Xycc\Winter\Contract\Attributes\Order;
use Xycc\Winter\Event\AbstractListener;
use Xycc\Winter\Event\Attributes\Listener;
use Xycc\Winter\Tests\Event\Events\AEvent;
use Xycc\Winter\Tests\Event\Events\BEvent;
use Xycc\Winter\Tests\Event\Events\StopEvent;

#[Order(value: 10)]
#[Listener(events: [AEvent::class, StopEvent::class, BEvent::class])]
class AListener extends AbstractListener
{
    public function handle(object $event)
    {
        $vars = get_class_vars(get_class($event));
        reset($vars);
        $var = key($vars);
        echo $event->{$var};
    }
}