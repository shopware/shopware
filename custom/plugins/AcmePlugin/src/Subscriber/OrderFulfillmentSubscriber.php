<?php declare(strict_types=1);

namespace Acme\AcmePlugin\Subscriber;

use Shopware\Core\System\StateMachine\Event\StateMachineTransitionedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Triggers Acme fulfillment logic when an order transitions to "shipped".
 * Registered at priority 50 so it runs after high-priority core listeners.
 *
 * Visible via: bin/console debug:event-dispatcher --event Shopware\Core\System\StateMachine\Event\StateMachineTransitionedEvent
 */
class OrderFulfillmentSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionedEvent::class => ['onTransition', 50],
        ];
    }

    public function onTransition(StateMachineTransitionedEvent $event): void
    {
        if ($event->getEntityName() === 'order'
            && $event->getToPlace()->getTechnicalName() === 'shipped') {
            // Acme fulfillment notification placeholder
        }
    }
}
