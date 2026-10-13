<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Resolves the price and the discount an analytics integration reports per product line item.
 *
 * Decorate it to change how promotion discounts are allocated to the products, for example for a
 * promotion type of an extension that does not store a composition.
 */
#[Package('discovery')]
abstract class AbstractAnalyticsLineItemPriceCalculator
{
    abstract public function getDecorated(): AbstractAnalyticsLineItemPriceCalculator;

    /**
     * @param iterable<LineItem|OrderLineItemEntity>|null $lineItems the top level line items of a
     *                                                               cart or an order, including the
     *                                                               discount line items
     */
    abstract public function calculate(?iterable $lineItems, SalesChannelContext $context): AnalyticsLineItemPriceCollection;
}
