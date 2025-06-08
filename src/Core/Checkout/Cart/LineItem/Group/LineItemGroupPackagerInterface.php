<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\LineItem\Group;

use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface LineItemGroupPackagerInterface
{
    /**
     * Gets the identifier key for this packager.
     * Every SetGroup with this packager key will use this packager.
     */
    public function getKey(): string;

    /**
     * Gets a list of line items that match the setup and conditions of this packager.
     * Iterate through the provided list of available line items and decide,
     * what items should be bundled in your package.
     * The resulting list of items will then be removed from the stack of available
     * available items when building other groups.
     */
    public function buildGroupPackage(float $value, LineItemFlatCollection $sortedItems, SalesChannelContext $context): LineItemGroup;
}
