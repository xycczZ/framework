<?php


namespace Xycc\Winter\Container\Exceptions;


use RuntimeException;
use Throwable;

class CycleDependencyException extends RuntimeException
{
    public function __construct($message = "", array $stack = [], $code = 0, Throwable $previous = null)
    {
        parent::__construct($message . json_encode($stack), $code, $previous);
    }
}