<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Event\Listeners;


use Xycc\Winter\Event\AbstractListener;
use Xycc\Winter\Event\Attributes\Listener;
use Xycc\Winter\Tests\Event\Events\AEvent;
use Xycc\Winter\Tests\Event\Events\StopEvent;

#[Listener(events: [AEvent::class, StopEvent::class])]
class BListener extends AbstractListener
{
    public function handle(object $event)
    {
        echo 'BListener';
    }
}