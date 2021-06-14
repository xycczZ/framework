<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AfterDate extends Rule
{
    public function __construct(
        public string $dateTime,
        public bool $eq = false,
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}