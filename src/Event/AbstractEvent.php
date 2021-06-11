<?php
declare(strict_types=1);

namespace Xycc\Winter\Event;


use Psr\EventDispatcher\StoppableEventInterface;

abstract class AbstractEvent implements StoppableEventInterface
{
    private bool $propagation = false;

    public function isPropagationStopped(): bool
    {
        return !$this->propagation;
    }

    /**
     * @param bool $propagation
     */
    public function setPropagation(bool $propagation): void
    {
        $this->propagation = $propagation;
    }
}