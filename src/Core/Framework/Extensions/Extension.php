<?php

namespace Shopware\Core\Framework\Extensions;

use Psr\EventDispatcher\StoppableEventInterface;

abstract class Extension implements StoppableEventInterface
{
    /**
     * @internal
     */
    abstract public static function name(): string;

    final public static function pre(): string
    {
        return static::name() . '.pre';
    }

    final public static function post(): string
    {
        return static::name() . '.post';
    }

    abstract public function result();

    public mixed $result = null;

    private bool $propagationStopped = false;

    /**
     * {@inheritdoc}
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Stops the propagation of the event to further event listeners.
     *
     * If multiple event listeners are connected to the same event, no
     * further event listener will be triggered once any trigger calls
     * stopPropagation().
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * @internal
     */
    public function resetPropagation(): void
    {
        $this->propagationStopped = false;
    }
}
