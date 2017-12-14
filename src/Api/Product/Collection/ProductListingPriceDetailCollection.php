<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Product\Struct\ProductListingPriceDetailStruct;

class ProductListingPriceDetailCollection extends ProductListingPriceBasicCollection
{
    /**
     * @var ProductListingPriceDetailStruct[]
     */
    protected $elements = [];

    public function getProducts(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductListingPriceDetailStruct $productListingPrice) {
                return $productListingPrice->getProduct();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductListingPriceDetailStruct::class;
    }
}
