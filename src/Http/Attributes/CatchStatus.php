<?php


namespace Xycc\Winter\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class CatchStatus
{
    public function __construct(
        public int $status,
        public ?string $exception = null
    )
    {
    }
}