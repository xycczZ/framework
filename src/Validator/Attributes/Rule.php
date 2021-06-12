<?php


namespace Xycc\Winter\Validator\Attributes;


abstract class Rule
{
    public function __construct(
        public array $scenes = ['default'],
        public string $errorMsg = '',
    )
    {
    }
}