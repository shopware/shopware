<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('business-ops')]
class BusinessEventCollector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function collect(Context $context): BusinessEventCollectorResponse
    {
        $events = $this->registry->getClasses();

        $result = new BusinessEventCollectorResponse();
        foreach ($events as $class) {
            $definition = $this->define($class);

            if (!$definition) {
                continue;
            }
            $result->set($definition->getName(), $definition);
        }

        // allows to mutate different events by plugins
        $event = new BusinessEventCollectorEvent($result, $context);
        $this->eventDispatcher->dispatch($event, BusinessEventCollectorEvent::NAME);

        $result = $event->getCollection();

        $result->sort(fn (BusinessEventDefinition $a, BusinessEventDefinition $b) => $a->getName() <=> $b->getName());

        return $result;
    }

    /**
     * @param class-string $class
     */
    public function define(string $class, ?string $name = null): ?BusinessEventDefinition
    {
        $instance = (new \ReflectionClass($class))
            ->newInstanceWithoutConstructor();

        if (!$instance instanceof FlowEventAware) {
            throw new \RuntimeException(sprintf('Event %s is not a business event', $class));
        }

        $name ??= $instance->getName();
        if (!$name) {
            return null;
        }

        /** @var array<class-string<object>> $interfaces */
        $interfaces = class_implements($instance);

        $aware = [];
        foreach ($interfaces as $interface) {
            if (is_subclass_of($interface, FlowEventAware::class)
                && $interface !== FlowEventAware::class) {
                $aware[] = lcfirst((new \ReflectionClass($interface))->getShortName());
                $aware[] = $interface;
            }
        }

        return new BusinessEventDefinition(
            $name,
            $class,
            $instance->getAvailableData()->toArray(),
            $aware
        );
    }
}
