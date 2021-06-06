<?php


namespace Xycc\Winter\Event\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Bean;

#[Attribute(Attribute::TARGET_CLASS)]
final class Event extends Bean
{
    public function __construct(public bool $runSeparateProcess = false)
    {
        parent::__construct();
    }
}