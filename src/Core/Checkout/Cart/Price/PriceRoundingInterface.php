<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

interface PriceRoundingInterface
{
    public function round(float $price, int $precision): float;
}
