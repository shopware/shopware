<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class DiscountPackager
{
    /**
     * This function should return the decorated core service.
     * This ensures that when new functions are implemented in this class, decorations will continue to work
     */
    abstract public function getDecorated(): DiscountPackager;

    /**
     * This function is used to get the line items that match the configured scope and product rules of the provided discount.
     * The result should be a list of found packager units including their actual line item composition.
     * So a SetGroup packager has found "Set Groups" as units (e.g. 3x "pants + tshirt" combo),
     * while a simple Cart packager might have only 1 result unit that contains all items.
     */
    abstract public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection;
}
