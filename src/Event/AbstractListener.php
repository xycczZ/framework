<?php


namespace Xycc\Winter\Event;


abstract class AbstractListener
{
    public abstract function handle(object $event);
}