<?php

namespace Shopware\ProductPrice\Struct;

class ProductListingPrice extends ProductPriceBasicStruct
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