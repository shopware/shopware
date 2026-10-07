<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Analytics;

use Shopware\Core\Framework\Log\Package;

/**
 * A product line item while {@see AnalyticsLineItemPriceCalculator} allocates the discounts to it.
 *
 * @internal
 */
#[Package('checkout')]
final class AnalyticsLineAllocation
{
    /**
     * @param float $total the line total before the discount
     * @param float $discount the discount allocated to the whole line, as a positive value
     */
    public function __construct(
        public readonly float $total,
        public readonly int $quantity,
        public float $discount,
    ) {
    }

    public function discountedTotal(): float
    {
        return $this->total - $this->discount;
    }
}
