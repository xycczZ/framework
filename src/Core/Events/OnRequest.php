<?php


namespace Xycc\Winter\Core\Events;

use Xycc\Winter\Core\SerializedRequest;
use Xycc\Winter\Event\AbstractEvent;
use Xycc\Winter\Event\Attributes\Event;

#[Event(true)]
class OnRequest extends AbstractEvent
{
    public function __construct(
        public SerializedRequest $request
    )
    {
    }

    public function __serialize(): array
    {
        return ['request' => $this->request];
    }

    public function __unserialize(array $data): void
    {
        $this->request = $data['request'];
    }
}