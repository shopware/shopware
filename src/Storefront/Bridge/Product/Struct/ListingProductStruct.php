<?php declare(strict_types=1);

namespace Shopware\Storefront\Bridge\Product\Struct;

class ListingProductStruct extends ProductBasicStruct
{
    /**
     * @var ListingPriceStruct
     */
    protected $listingPrice;

    public function getListingPrice(): ListingPriceStruct
    {
        return $this->listingPrice;
    }

    public function setListingPrice(ListingPriceStruct $listingPrice): void
    {
        $this->listingPrice = $listingPrice;
    }
}