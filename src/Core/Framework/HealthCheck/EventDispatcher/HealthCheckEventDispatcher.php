<?php


namespace Shopware\Core\Framework\HealthCheck\EventDispatcher;

use Shopware\Core\Framework\HealthCheck\Subscriber\HealthCheckEventSubscriber;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class HealthCheckEventDispatcher implements EventDispatcherInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly HealthCheckEventSubscriber $healthCheckEventSubscriber
    ) {
    }

    public function dispatch(object $event, string $eventName = null): object
    {
        $this->eventDispatcher->addSubscriber($this->healthCheckEventSubscriber);

        return $this->eventDispatcher->dispatch($event, $eventName);
    }
}
