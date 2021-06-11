<?php


namespace Xycc\Winter\Event\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Bean;

#[Attribute(Attribute::TARGET_CLASS)]
final class Listener extends Bean
{
    public array $events = [];

    public function __construct(public ?string $value = null, $events)
    {
        $this->events = $events;
    }
}