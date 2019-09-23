<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface DiscountPackagerInterface
{
    /**
     * This function is used to get the line items that match the configured
     * scope and product rules of the provided discount.
     * All line items that are valid for the discount line item should be returned as a
     * new line item collection.
     */
    public function getMatchingItems(LineItem $discount, Cart $cart, SalesChannelContext $context): array;
}
