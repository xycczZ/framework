<?php


namespace Xycc\Winter\Container\Exceptions;


use RuntimeException;
use Throwable;

class DuplicatedIdentityException extends RuntimeException
{
    public function __construct(?string $type, array $names = [], $message = '', $code = 0, Throwable $previous = null)
    {
        if ($type) {
            $message .= '解析' . $type . '时, ';
        }
        $msg = sprintf('Bean 名字冲突 %s', implode(',', $names));
        parent::__construct($message . $msg, $code, $previous);
    }
}