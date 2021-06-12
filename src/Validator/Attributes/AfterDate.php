<?php


namespace Xycc\Winter\Validator\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AfterDate extends ValidationRule
{
    public function __construct(
        public \DateTimeInterface $dateTime,
        public bool $eq = false,
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}