<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Event\CustomAppEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('services-settings')]
class BusinessEventCollector
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventRegistry $registry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly Connection $connection
    ) {
    }

    public function collect(Context $context): BusinessEventCollectorResponse
    {
        $events = $this->registry->getClasses();

        $result = new BusinessEventCollectorResponse();
        $result = $this->fetchAppEvents($result);

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
            throw new \RuntimeException(\sprintf('Event %s is not a business event', $class));
        }

        $name ??= $instance->getName();
        if (!$name) {
            return null;
        }

        $interfaces = class_implements($instance) ?: [];

        $aware = [];
        foreach ($interfaces as $interface) {
            $reflection = new \ReflectionClass($interface);
            if ($reflection->getAttributes(IsFlowEventAware::class) !== []) {
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

    private function fetchAppEvents(BusinessEventCollectorResponse $result): BusinessEventCollectorResponse
    {
        $appEvents = $this->connection->fetchAllAssociative('SELECT `app_flow_event`.`name`, `app_flow_event`.`aware` FROM `app_flow_event` JOIN `app` ON `app_flow_event`.`app_id` = `app`.`id` WHERE `app`.`active` = 1');

        array_map(function ($event) use ($result): void {
            $definition = new BusinessEventDefinition(
                $event['name'],
                CustomAppEvent::class,
                [],
                json_decode($event['aware'], true) ?? []
            );

            if (!$result->get($definition->getName())) {
                $result->set($definition->getName(), $definition);
            }
        }, $appEvents);

        return $result;
    }
}
