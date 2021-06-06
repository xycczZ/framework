<?php


namespace Xycc\Winter\Http\Attributes;

use Attribute;
use Xycc\Winter\Contract\Attributes\Bean;

#[Attribute(Attribute::TARGET_CLASS)]
class ExceptionHandler extends Bean
{
    public function __construct()
    {
        parent::__construct();
    }
}