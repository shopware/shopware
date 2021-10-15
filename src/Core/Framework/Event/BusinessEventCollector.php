<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\LogAwareBusinessEventInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BusinessEventCollector
{
    /**
     * @var BusinessEventRegistry
     */
    private $registry;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        BusinessEventRegistry $registry,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->registry = $registry;
        $this->eventDispatcher = $eventDispatcher;
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

        $result->sort(function (BusinessEventDefinition $a, BusinessEventDefinition $b) {
            return $a->getName() <=> $b->getName();
        });

        return $result;
    }

    /**
     * @param class-string $class
     */
    public function define(string $class, ?string $name = null): ?BusinessEventDefinition
    {
        if ($class === BusinessEvent::class) {
            return null;
        }

        $instance = (new \ReflectionClass($class))
            ->newInstanceWithoutConstructor();

        if (Feature::isActive('FEATURE_NEXT_17858')) {
            if (!$instance instanceof FlowEventAware) {
                throw new \RuntimeException(sprintf('Event %s is not a business event', $class));
            }
        } else {
            if (!$instance instanceof BusinessEventInterface) {
                throw new \RuntimeException(sprintf('Event %s is not a business event', $class));
            }
        }

        $name = $name ?? $instance->getName();
        if (!$name) {
            return null;
        }

        /** @var array $interfaces */
        $interfaces = class_implements($instance);

        $aware = [];
        foreach ($interfaces as $interface) {
            if (is_subclass_of($interface, FlowEventAware::class)
                && $interface !== FlowEventAware::class
                && !is_subclass_of($interface, BusinessEventInterface::class)
                && $interface !== BusinessEventInterface::class) {
                $aware[] = $interface;
            }
        }

        return new BusinessEventDefinition(
            $name,
            $class,
            $instance instanceof MailActionInterface,
            $instance instanceof LogAwareBusinessEventInterface,
            $instance instanceof SalesChannelAware,
            $instance::getAvailableData()->toArray(),
            $aware
        );
    }
}
