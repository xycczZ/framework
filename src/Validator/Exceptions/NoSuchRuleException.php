<?php


namespace Xycc\Winter\Validator\Exceptions;


use RuntimeException;
use Throwable;
use Xycc\Winter\Validator\Validator;

class NoSuchRuleException extends RuntimeException
{
    public function __construct(string $rule = '', $code = 0, Throwable $previous = null)
    {
        $message = sprintf('No such rule "%s", consider extends %s add yourself-defined rule', $rule, Validator::class);
        parent::__construct($message, $code, $previous);
    }
}