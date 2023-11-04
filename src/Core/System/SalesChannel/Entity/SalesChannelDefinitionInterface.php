<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('sales-channel')]
interface SalesChannelDefinitionInterface
{
    /**
     * Called after the api prepared the criteria for the repository.
     * It is possible to remove associations, filters or sortings, throw exception for invalid access
     * or adding some base conditions to filter only active entities or only entities which are relate to the
     * current sales channel id.
     *
     * @example
     *      $criteria->addFilter(new EqualsFilter('product.active', true));
     *      $criteria->addFilter(new EqualsFilter('currency.salesChannel.id', $context->getSalesChannel()->getId())
     */
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void;
}
