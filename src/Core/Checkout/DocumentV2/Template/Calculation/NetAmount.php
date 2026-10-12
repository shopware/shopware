<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Calculation;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * Derives the net base amount of a Shopware calculated tax row.
 *
 * @internal
 */
#[Package('after-sales')]
final readonly class NetAmount
{
    /**
     * Returns true when the cart was priced in gross mode (taxes are subtracted from line prices
     * to derive the net amount). Tax-free orders use the net path as well.
     */
    public static function isOrderGross(OrderEntity $order): bool
    {
        return !$order->getPrice()->hasNetPrices();
    }

    /**
     * Returns the net amount for a single tax row.
     *
     * If `$tax` is null we fall back to the first calculated tax on `$fallbackPrice`; if there
     * are no taxes at all, the price's total is returned as-is (tax-free lines).
     */
    public static function fromTax(
        ?CalculatedTax $tax,
        ?CalculatedPrice $fallbackPrice,
        bool $isGross,
    ): float {
        $tax ??= $fallbackPrice?->getCalculatedTaxes()->first();

        if ($tax === null) {
            return $fallbackPrice?->getTotalPrice() ?? 0.0;
        }

        return $isGross
            ? $tax->getPrice() - $tax->getTax()
            : $tax->getPrice();
    }
}
