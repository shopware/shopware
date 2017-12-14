<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Product\Struct\ProductPriceDetailStruct;

class ProductPriceDetailCollection extends ProductPriceBasicCollection
{
    /**
     * @var ProductPriceDetailStruct[]
     */
    protected $elements = [];

    public function getProducts(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductPriceDetailStruct $productPrice) {
                return $productPrice->getProduct();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductPriceDetailStruct::class;
    }
}
