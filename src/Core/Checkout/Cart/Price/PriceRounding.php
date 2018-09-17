<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

class PriceRounding
{
    /**
     * @var int
     */
    protected $precisions;

    public function __construct(int $precisions)
    {
        $this->precisions = $precisions;
    }

    public function round(float $price): float
    {
        return round($price, $this->precisions);
    }
}
