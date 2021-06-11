<?php


namespace Xycc\Winter\Event\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Event
{
    public function __construct(public bool $runSeparateProcess = false)
    {
    }
}