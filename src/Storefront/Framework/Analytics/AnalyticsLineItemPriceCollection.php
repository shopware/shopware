<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * The analytics prices of the product line items of a cart or an order, keyed by line item id.
 *
 * @extends Collection<AnalyticsLineItemPrice>
 */
#[Package('discovery')]
class AnalyticsLineItemPriceCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'storefront_analytics_line_item_price_collection';
    }

    protected function getExpectedClass(): string
    {
        return AnalyticsLineItemPrice::class;
    }
}
