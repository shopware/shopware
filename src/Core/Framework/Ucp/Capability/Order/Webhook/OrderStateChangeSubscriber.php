<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Order\Webhook;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Subscribes to:
 *   1. `CheckoutOrderPlacedEvent` — fires the initial "order created" webhook
 *      for newly-placed orders.
 *   2. `StateMachineTransitionEvent` — fires "order updated" webhooks for any
 *      subsequent order, order_delivery, or order_transaction state change.
 *
 * @internal
 */
#[Package('framework')]
class OrderStateChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly OrderWebhookPublisher $publisher,
        private readonly EntityRepository $orderRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
            StateMachineTransitionEvent::class => 'onTransition',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return;
        }

        $this->dispatchWebhook($event->getOrder()->getId(), $event->getContext());
    }

    public function onTransition(StateMachineTransitionEvent $event): void
    {
        if (!Feature::isActive('UCP_SERVER')) {
            return;
        }
        if ($event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        $this->dispatchWebhook($event->getEntityId(), $event->getContext());
    }

    private function dispatchWebhook(string $orderId, Context $context): void
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries.stateMachineState')
            ->addAssociation('orderCustomer')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState');

        $order = $this->orderRepository->search($criteria, $context)->first();
        if ($order instanceof OrderEntity) {
            $this->publisher->publish($order, $context);
        }
    }
}
