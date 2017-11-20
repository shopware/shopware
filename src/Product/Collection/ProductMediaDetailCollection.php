<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Product\Struct\ProductMediaDetailStruct;

class ProductMediaDetailCollection extends ProductMediaBasicCollection
{
    /**
     * @var ProductMediaDetailStruct[]
     */
    protected $elements = [];

    public function getProducts(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductMediaDetailStruct $productMedia) {
                return $productMedia->getProduct();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductMediaDetailStruct::class;
    }
}
