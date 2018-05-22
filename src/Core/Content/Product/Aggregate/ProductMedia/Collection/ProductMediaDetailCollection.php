<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductMedia\Collection;

use Shopware\Content\Product\Aggregate\ProductMedia\Struct\ProductMediaDetailStruct;
use Shopware\Content\Product\Collection\ProductBasicCollection;

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
