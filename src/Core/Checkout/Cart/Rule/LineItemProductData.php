<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class LineItemProductData
{
    private const TYPES_WITHOUT_PRODUCT_DATA = [
        LineItem::CONTAINER_LINE_ITEM,
        'customized-products-option',
        'option-values',
    ];

    public static function isExcludedFromProductConditions(LineItem $lineItem): bool
    {
        return \in_array($lineItem->getType(), self::TYPES_WITHOUT_PRODUCT_DATA, true);
    }
}
