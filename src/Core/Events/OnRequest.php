<?php


namespace Xycc\Winter\Core\Events;

use Swoole\Http\Request;
use Xycc\Winter\Contract\Attributes\Scope;
use Xycc\Winter\Event\AbstractEvent;
use Xycc\Winter\Event\Attributes\Event;

#[Event]
#[Scope(Scope::SCOPE_PROTOTYPE, Scope::MODE_PROXY)]
class OnRequest extends AbstractEvent
{
    public function __construct(
        public Request $request
    )
    {
    }
}