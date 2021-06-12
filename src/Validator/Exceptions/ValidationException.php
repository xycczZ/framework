<?php


namespace Xycc\Winter\Validator\Exceptions;


use RuntimeException;
use Throwable;

class ValidationException extends RuntimeException
{
    public $messageBags;

    public function __construct($messageBags, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->messageBags = $messageBags;
        parent::__construct($message, $code, $previous);
    }
}