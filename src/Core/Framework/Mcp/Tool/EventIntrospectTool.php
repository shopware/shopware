<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(
    name: 'shopware-event-introspect',
    description: 'Inspect a Shopware event class via PHP Reflection: constructor parameters, available getter methods, active listeners registered in this instance with their priorities, and a ready-to-use subscriber skeleton. Accepts an exact FQCN or a partial class name (e.g. "StateMachineTransitioned", "ProductLoaded"). Returns {success, data: {event_class, constructor_params, available_getters, active_listeners_in_this_instance, subscriber_skeleton}}.',
)]
#[Package('framework')]
class EventIntrospectTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(string $eventClass): string
    {
        $resolved = $this->resolveEventClass($eventClass);

        if ($resolved === null) {
            return $this->error(\sprintf(
                'Event class not found matching "%s". '
                . 'Try a partial name (e.g. "StateMachineTransitioned") '
                . 'or use the shopware://business-events resource to list available events.',
                $eventClass,
            ));
        }

        $reflection = new \ReflectionClass($resolved);

        return $this->success([
            'event_class' => $resolved,
            'short_name' => $reflection->getShortName(),
            'constructor_params' => $this->describeConstructorParams($reflection),
            'available_getters' => $this->extractGetters($reflection),
            'active_listeners_in_this_instance' => $this->getActiveListeners($resolved),
            'subscriber_skeleton' => $this->generateSubscriberSkeleton($resolved),
            'tip' => 'Run debug:event-dispatcher to see all listener priorities across every event.',
        ]);
    }

    private function resolveEventClass(string $partial): ?string
    {
        if (class_exists($partial)) {
            return $partial;
        }

        foreach (array_keys($this->eventDispatcher->getListeners()) as $eventName) {
            if (!class_exists($eventName)) {
                continue;
            }
            if (str_contains($eventName, $partial) || str_ends_with($eventName, $partial)) {
                return $eventName;
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, type: string, optional: bool}>
     */
    private function describeConstructorParams(\ReflectionClass $reflection): array
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $params = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            $params[] = [
                'name' => $param->getName(),
                'type' => $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed',
                'optional' => $param->isOptional(),
            ];
        }

        return $params;
    }

    /**
     * @return list<array{name: string, returns: string}>
     */
    private function extractGetters(\ReflectionClass $reflection): array
    {
        $getters = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!str_starts_with($method->getName(), 'get')) {
                continue;
            }
            if ($method->getNumberOfParameters() > 0) {
                continue;
            }
            $returnType = $method->getReturnType();
            $getters[] = [
                'name' => $method->getName(),
                'returns' => $returnType instanceof \ReflectionNamedType ? $returnType->getName() : 'mixed',
            ];
        }

        return $getters;
    }

    /**
     * @return list<array{class: string, method: string, priority: int}>
     */
    private function getActiveListeners(string $eventClass): array
    {
        $listeners = [];
        foreach ($this->eventDispatcher->getListeners($eventClass) as $listener) {
            if (!\is_array($listener)) {
                continue;
            }
            [$service, $method] = $listener;
            $listeners[] = [
                'class' => \is_object($service) ? $service::class : (string) $service,
                'method' => $method,
                'priority' => $this->eventDispatcher->getListenerPriority($eventClass, $listener) ?? 0,
            ];
        }

        usort($listeners, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return $listeners;
    }

    private function generateSubscriberSkeleton(string $eventClass): string
    {
        $reflection = new \ReflectionClass($eventClass);
        $shortName = $reflection->getShortName();
        $listeners = $this->getActiveListeners($eventClass);
        $maxPriority = $listeners !== [] ? max(array_column($listeners, 'priority')) : 0;
        $suggested = $maxPriority + 10;

        $priorityComment = $listeners === []
            ? '// No other listeners registered on this event yet'
            : \sprintf(
                '// Priority %d — runs before all current listeners (highest existing priority: %d)',
                $suggested,
                $maxPriority,
            );

        return <<<PHP
            use {$eventClass};
            use Symfony\Component\EventDispatcher\EventSubscriberInterface;

            class YourSubscriber implements EventSubscriberInterface
            {
                public static function getSubscribedEvents(): array
                {
                    return [{$shortName}::class => ['onEvent', {$suggested}]];
                }

                public function onEvent({$shortName} \$event): void
                {
                    {$priorityComment}
                    // Access event data via getters listed in available_getters above
                }
            }
            PHP;
    }
}
