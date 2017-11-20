<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Product\Struct\ProductTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class ProductTranslationDetailCollection extends ProductTranslationBasicCollection
{
    /**
     * @var ProductTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getProducts(): ProductBasicCollection
    {
        return new ProductBasicCollection(
            $this->fmap(function (ProductTranslationDetailStruct $productTranslation) {
                return $productTranslation->getProduct();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ProductTranslationDetailStruct $productTranslation) {
                return $productTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductTranslationDetailStruct::class;
    }
}
