<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Telemetry\Metrics;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Extractor\MetricExtractor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('core')]
readonly class MetricEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private MetricExtractor $metricExtractor,
        private Meter $meter
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $result = $this->eventDispatcher->dispatch($event, $eventName);

        Feature::ifActive('TELEMETRY_METRICS', function () use ($event): void {
            $metrics = $this->metricExtractor->fromEvent($event);
            foreach ($metrics as $metric) {
                $this->meter->emit($metric);
            }
        });

        return $result;
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->eventDispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->eventDispatcher->hasListeners($eventName);
    }

    /**
     * @param callable(): mixed $listener
     */
    public function addListener(string $eventName, array|callable $listener, int $priority = 0): void
    {
        $this->eventDispatcher->addListener($eventName, $listener, $priority);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->eventDispatcher->addSubscriber($subscriber);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->eventDispatcher->removeListener($eventName, $listener);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->eventDispatcher->removeSubscriber($subscriber);
    }

    public function getListeners(?string $eventName = null): array
    {
        return $this->eventDispatcher->getListeners($eventName);
    }
}
