<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderCriteriaBuilder
{
    public function __construct(private readonly EventDispatcherInterface $dispatcher)
    {
    }

    public function getCriteria(string $entityId, Context $context): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'primaryOrderDelivery',
            'primaryOrderTransaction',
            'orderCustomer',
            'orderCustomer.salutation',
            'lineItems.downloads.media',
            'lineItems.cover',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingOrderAddress.countryState',
            'stateMachineState',
            'transactions.stateMachineState',
            'transactions.paymentMethod',
            'deliveries.stateMachineState',
            'currency',
            'addresses.country',
            'addresses.countryState',
            'tags',
            'documents',
        ]);

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        $event = new MailFlowDataCriteriaEvent(
            OrderDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        return $criteria;
    }
}
