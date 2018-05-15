<?php declare(strict_types=1);

namespace Shopware\Content\Product\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\Content\Product\Struct\ProductTranslationDetailStruct;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
