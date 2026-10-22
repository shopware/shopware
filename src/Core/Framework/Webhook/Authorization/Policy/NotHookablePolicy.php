<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Authorization\Policy;

use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\SubscriberType;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\NotHookable;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * Handles every event carrying the #[NotHookable] attribute and denies it. The event names
 * are resolved from the attribute on first use and memoized for the lifetime of the process.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class NotHookablePolicy implements Policy
{
    /**
     * @var list<string>|null
     */
    private ?array $eventNames = null;

    public function __construct(private readonly BusinessEventRegistry $eventRegistry)
    {
    }

    public function handles(string $eventName): bool
    {
        return \in_array($eventName, $this->eventNames ??= $this->resolveEventNames(), true);
    }

    public function permitsSubscription(string $eventName, Subscriber $subscriber): bool
    {
        // @deprecated tag:v6.8.0 - Will always return false, apps can no longer subscribe to events that are not hookable
        if ($subscriber->type !== SubscriberType::App || Feature::isActive('v6.8.0.0')) {
            return false;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf('Webhooks can not subscribe to "%s", the event is not hookable. The subscription is ignored and will be refused in v6.8.0.0.', $eventName),
        );

        return true;
    }

    public function permitsDelivery(Hookable $event, Webhook $webhook): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    private function resolveEventNames(): array
    {
        $eventNames = [];
        foreach ($this->eventRegistry->getClasses() as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if ($reflection->getAttributes(NotHookable::class) === []) {
                continue;
            }

            $event = $reflection->newInstanceWithoutConstructor();
            \assert($event instanceof FlowEventAware);

            $eventNames[] = $event->getName();
        }

        return array_values(array_unique($eventNames));
    }
}
