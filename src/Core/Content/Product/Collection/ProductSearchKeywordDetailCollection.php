<?php declare(strict_types=1);

namespace Shopware\Content\Product\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\Content\Product\Struct\ProductSearchKeywordDetailStruct;

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
