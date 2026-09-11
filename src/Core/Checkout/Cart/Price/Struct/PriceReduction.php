<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Shared calculation for reference prices (e.g. list price, regulation price) that express a
 * saving relative to the current unit price. The reference price must be greater than zero;
 * callers are expected to guard against that before calculating a percentage.
 */
#[Package('checkout')]
final class PriceReduction
{
    public static function discount(float $unitPrice, float $referencePrice): float
    {
        return ($referencePrice - $unitPrice) * -1;
    }

    public static function percentage(float $unitPrice, float $referencePrice): float
    {
        return round(100 - $unitPrice / $referencePrice * 100, 2);
    }
}
