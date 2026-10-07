<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Framework\Log\Package;

/**
 * The prices an analytics integration reports for a product line item, see
 * {@see AnalyticsLineItemPriceCalculator}.
 *
 * @internal
 */
#[Package('checkout')]
final readonly class AnalyticsLineItemPrice
{
    /**
     * @param float $price the unit price after the discount
     * @param float $discount the discount per unit
     * @param float $total the paid line total, which the rounded unit price times the quantity can miss by a cent
     */
    public function __construct(
        public float $price,
        public float $discount,
        public float $total,
    ) {
    }
}
