<?php


namespace Xycc\Winter\Event\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;

#[Attribute(Attribute::TARGET_CLASS)]
#[NoProxy]
final class Listener extends Component
{
    public array $events = [];

    public function __construct(public ?string $value = null, $events)
    {
        $this->events = $events;
    }
}