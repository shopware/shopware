<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class CustomerCriteriaBuilder
{
    public function getCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'salutation',
            'defaultBillingAddress.country',
            'defaultBillingAddress.countryState',
            'defaultBillingAddress.salutation',
            'defaultShippingAddress.country',
            'defaultShippingAddress.countryState',
            'defaultShippingAddress.salutation',
        ]);

        return $criteria;
    }
}
