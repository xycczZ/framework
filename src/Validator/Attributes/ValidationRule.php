<?php


namespace Xycc\Winter\Validator\Attributes;


abstract class ValidationRule
{
    public function __construct(
        public string $scene = '',
        public string $errorMsg = '',
    )
    {
    }
}