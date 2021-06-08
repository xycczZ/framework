<?php


namespace Xycc\Winter\Core\Events;

use Swoole\Http\Request;
use Xycc\Winter\Contract\Attributes\Lazy;
use Xycc\Winter\Event\AbstractEvent;
use Xycc\Winter\Event\Attributes\Event;

#[Event]
class OnRequest extends AbstractEvent
{
    public function __construct(
        public Request $request
    )
    {
    }
}