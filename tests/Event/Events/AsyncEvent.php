<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Event\Events;


//#[Event(true)]
class AsyncEvent
{
    public function __construct(public string $async)
    {
    }
}