<?php
declare(strict_types=1);

namespace Xycc\Winter\Aspect\Exceptions;


use Throwable;

class NotFoundFileException extends \RuntimeException
{
    public function __construct(string $class, $message = "", $code = 0, Throwable $previous = null)
    {
        $message = sprintf('not found class file, class: %s', $class);
        parent::__construct($message, $code, $previous);
    }
}