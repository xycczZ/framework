<?php
declare(strict_types=1);

namespace Xycc\Winter\Event;


use Psr\EventDispatcher\StoppableEventInterface;

abstract class AbstractEvent implements StoppableEventInterface, \Serializable, \JsonSerializable
{
    private bool $propagation = false;

    public function serialize(): ?string
    {
        return serialize($this);
    }

    public function jsonSerialize(): string
    {
        return json_encode($this, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }

    public function unserialize($serialized)
    {
        return unserialize($serialized);
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagation;
    }

    /**
     * @param bool $propagation
     */
    public function setPropagation(bool $propagation): void
    {
        $this->propagation = $propagation;
    }
}