<?php


namespace Xycc\Winter\Event\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Bean;

#[Attribute(Attribute::TARGET_CLASS)]
final class Listener extends Bean
{
    public array $events = [];

    public function __construct(...$events)
    {
        parent::__construct();
        $this->events = $events;
    }
}