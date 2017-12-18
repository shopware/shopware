<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Product\Struct\ProductSearchKeywordDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class ProductSearchKeywordDetailCollection extends ProductSearchKeywordBasicCollection
{
    /**
     * @var ProductSearchKeywordDetailStruct[]
     */
    protected $elements = [];

    public function getShops(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ProductSearchKeywordDetailStruct $productSearchKeyword) {
                return $productSearchKeyword->getShop();
            })
        );
    }

    public function getProducts(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductSearchKeywordDetailStruct $productSearchKeyword) {
                return $productSearchKeyword->getProduct();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductSearchKeywordDetailStruct::class;
    }
}
