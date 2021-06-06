<?php
declare(strict_types=1);

namespace Xycc\Winter\Tests\Event\Events;


use Xycc\Winter\Event\AbstractEvent;
use Xycc\Winter\Event\Attributes\Event;

#[Event]
class BEvent extends AbstractEvent
{
    public function __construct(public string $b)
    {
    }
}