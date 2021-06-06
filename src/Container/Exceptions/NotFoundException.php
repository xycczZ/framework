<?php


namespace Xycc\Winter\Container\Exceptions;


use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    public function __construct(private string $bean = '', $message = '', $code = 0, Throwable $previous = null)
    {
        $message = $message ?: 'Bean not found: ' . $bean;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getBean(): string
    {
        return $this->bean;
    }
}