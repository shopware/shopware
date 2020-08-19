<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

/**
 * @deprecated tag:v6.4.0 - Use \Shopware\Core\Checkout\Cart\Price\CashRounding instead
 */
class PriceRounding implements PriceRoundingInterface
{
    public function round(float $price, int $precision): float
    {
        return round($price, $precision);
    }
}
