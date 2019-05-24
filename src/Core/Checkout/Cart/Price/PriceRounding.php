<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

class PriceRounding implements PriceRoundingInterface
{
    public function round(float $price, int $precision): float
    {
        return round($price, $precision);
    }
}
