<?php


namespace Xycc\Winter\Event\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Bean;
use Xycc\Winter\Contract\Attributes\Scope;

#[Attribute(Attribute::TARGET_CLASS)]
#[Scope(Scope::SCOPE_PROTOTYPE, Scope::MODE_PROXY)]
final class Event extends Bean
{
    public function __construct(public bool $runSeparateProcess = false)
    {
        parent::__construct();
    }
}