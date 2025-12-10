<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderCriteriaBuilder
{
    public function getCriteria(string $entityId): Criteria
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

        return $criteria;
    }
}
