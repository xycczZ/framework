<?php


namespace Xycc\Winter\Container\Exceptions;


use Psr\Container\ContainerExceptionInterface;

class UntypedLazyException extends \RuntimeException implements ContainerExceptionInterface
{
}