<?php

namespace Xycc\Winter\Command\Attributes;

use Attribute;
use Symfony\Component\Console\Attribute\AsCommand as SymfonyAsCommand;
use Xycc\Winter\Contract\Attributes\Component;
use Xycc\Winter\Contract\Attributes\NoProxy;
use Xycc\Winter\Contract\Attributes\Scope;

#[Component]
#[NoProxy]
#[Scope(Scope::SCOPE_PROTOTYPE, Scope::MODE_PROXY)]
#[Attribute(Attribute::TARGET_CLASS)]
class AsCommand extends SymfonyAsCommand
{

}