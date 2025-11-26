<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\DataProvider;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderProvider implements DataProvider
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
    ){
    }

    public function supports(string $entityName): bool
    {
        return $entityName === OrderDefinition::ENTITY_NAME;
    }

    public function getData(string $entityId, Context $context): Entity
    {
        // TODO: Same as OrderStorer, we should move it to a common place
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

        return $this->orderRepository->search($criteria, $context)->getEntities()->get($entityId);
    }
}
