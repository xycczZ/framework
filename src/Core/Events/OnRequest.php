<?php


namespace Xycc\Winter\Core\Events;

use Xycc\Winter\Event\AbstractEvent;
use Xycc\Winter\Event\Attributes\Event;
use Xycc\Winter\Http\Request\Request;

#[Event(true)]
class OnRequest extends AbstractEvent
{
    public function __construct(
        public Request $request
    )
    {
    }
}