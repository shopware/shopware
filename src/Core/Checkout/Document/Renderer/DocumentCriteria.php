<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Renderer;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class DocumentCriteria extends Criteria
{
    public function __construct(string $deepLinkCode = '', ?array $ids = null)
    {
        parent::__construct($ids);

        $this->addAssociations([
            'lineItems',
            'transactions.paymentMethod',
            'currency',
            'language.locale',
            'addresses.country',
            'deliveries.positions',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'orderCustomer.customer',
        ]);

        $this->getAssociation('lineItems')->addSorting(new FieldSorting('position'));
        $this->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        $this->getAssociation('deliveries')->addSorting(new FieldSorting('createdAt'));

        if ($deepLinkCode !== '') {
            $this->addFilter(new EqualsFilter('deepLinkCode', $deepLinkCode));
        }
    }
}
