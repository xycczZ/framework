<?php


namespace Xycc\Winter\Container\Exceptions;


use RuntimeException;
use Throwable;

class DuplicatedIdentityException extends RuntimeException
{
    public function __construct(?string $type, ?string $name, $message = '', $code = 0, Throwable $previous = null)
    {
        $message .= 'type: ' . ($type ?: 'null') . ', name: ' . ($name ?: 'null');
        parent::__construct($message, $code, $previous);
    }
}