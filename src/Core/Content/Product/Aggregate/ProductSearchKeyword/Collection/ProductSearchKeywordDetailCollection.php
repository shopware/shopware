<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductSearchKeyword\Collection;

use Shopware\System\Language\Collection\LanguageBasicCollection;
use Shopware\Content\Product\Aggregate\ProductSearchKeyword\Struct\ProductSearchKeywordDetailStruct;
use Shopware\Content\Product\Collection\ProductBasicCollection;

class ProductSearchKeywordDetailCollection extends ProductSearchKeywordBasicCollection
{
    /**
     * @var ProductSearchKeywordDetailStruct[]
     */
    protected $elements = [];

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (ProductSearchKeywordDetailStruct $productSearchKeyword) {
                return $productSearchKeyword->getLanguage();
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
