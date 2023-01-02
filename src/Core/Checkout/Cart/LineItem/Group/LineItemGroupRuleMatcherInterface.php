<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface LineItemGroupRuleMatcherInterface
{
    /**
     * Gets a list of line items that match for the provided group object.
     * You can use AND conditions, OR conditions, or anything else, depending on your implementation.
     */
    public function getMatchingItems(LineItemGroupDefinition $groupDefinition, LineItemFlatCollection $items, SalesChannelContext $context): LineItemFlatCollection;
}
