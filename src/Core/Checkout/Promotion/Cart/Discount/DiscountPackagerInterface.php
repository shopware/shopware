<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface DiscountPackagerInterface
{
    /**
     * Use this context if your result filter should be based
     * on the whole found packages of your packager.
     */
    public const RESULT_CONTEXT_PACKAGE = 'package';

    /**
     * Use this context if the result filter should be based
     * on each line item inside your package.
     */
    public const RESULT_CONTEXT_LINEITEM = 'lineitem';

    /**
     * Gets the scope of this packager for filtering.
     * This defines, what should actually be filtered from the result, either the
     * line items directly, or the resulting packages that are returned.
     * In case of a CartPackager, the line items in the single package should be filtered.
     * In case of a GroupPackager, the whole groups should be filtered that have been found.
     */
    public function getResultContext(): string;

    /**
     * This function is used to get the line items that match the configured scope and product rules of the provided discount.
     * The result should be a list of found packager units including their actual line item composition.
     * So a SetGroup packager has found "Set Groups" as units (e.g. 3x "pants + tshirt" combo),
     * while a simple Cart packager might have only 1 result unit that contains all items.
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection;
}
