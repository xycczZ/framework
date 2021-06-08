<?php


namespace Xycc\Winter\Container\Exceptions;


use RuntimeException;
use Throwable;

class DuplicatedIdentityException extends RuntimeException
{
    public function __construct(?string $type, ?string $name, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Bean 名字冲突 %s', $type ?? $name);
        parent::__construct($message, $code, $previous);
    }
}