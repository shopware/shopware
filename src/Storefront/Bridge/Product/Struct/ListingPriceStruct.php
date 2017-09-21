<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Struct;

use Shopware\ProductDetailPrice\Struct\ProductDetailPriceBasicStruct;

class ListingPriceStruct extends ProductDetailPriceBasicStruct
{
    /**
     * @var bool
     */
    protected $hasDifferentPrices = false;

    public function isHasDifferentPrices(): bool
    {
        return $this->hasDifferentPrices;
    }

    public function setHasDifferentPrices(bool $hasDifferentPrices): void
    {
        $this->hasDifferentPrices = $hasDifferentPrices;
    }
}
