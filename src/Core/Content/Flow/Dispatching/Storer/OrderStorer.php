<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Storer;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\BeforeLoadStorableFlowDataEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('business-ops')]
class OrderStorer extends FlowStorer
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof OrderAware || isset($stored[OrderAware::ORDER_ID])) {
            return $stored;
        }

        $stored[OrderAware::ORDER_ID] = $event->getOrderId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(OrderAware::ORDER_ID)) {
            return;
        }

        $storable->setData(OrderAware::ORDER_ID, $storable->getStore(OrderAware::ORDER_ID));

        $storable->lazy(
            OrderAware::ORDER,
            $this->lazyLoad(...)
        );
    }

    /**
     * @param array<int, mixed> $args
     *
     * @deprecated tag:v6.6.0 - Will be removed in v6.6.0.0
     */
    public function load(array $args): ?OrderEntity
    {
        Feature::triggerDeprecationOrThrow(
            'v6_6_0_0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0.0')
        );

        [$orderId, $context] = $args;

        $criteria = new Criteria([$orderId]);

        return $this->loadOrder($criteria, $context, $orderId);
    }

    private function lazyLoad(StorableFlow $storableFlow): ?OrderEntity
    {
        $id = $storableFlow->getStore(OrderAware::ORDER_ID);
        if ($id === null) {
            return null;
        }

        $criteria = new Criteria([$id]);

        return $this->loadOrder($criteria, $storableFlow->getContext(), $id);
    }

    private function loadOrder(Criteria $criteria, Context $context, string $orderId): ?OrderEntity
    {
        $criteria->addAssociations([
            'orderCustomer',
            'orderCustomer.salutation',
            'lineItems.downloads.media',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingOrderAddress.countryState',
            'stateMachineState',
            'transactions.stateMachineState',
            'transactions.paymentMethod',
            'deliveries.stateMachineState',
            'currency',
            'addresses.country',
            'tags',
        ]);

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        $event = new BeforeLoadStorableFlowDataEvent(
            OrderDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        $order = $this->orderRepository->search($criteria, $context)->get($orderId);

        if ($order) {
            /** @var OrderEntity $order */
            return $order;
        }

        return null;
    }
}
