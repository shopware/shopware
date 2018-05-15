<?php declare(strict_types=1);

namespace Shopware\Content\Product\Collection;

use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Content\Product\Struct\ProductManufacturerTranslationDetailStruct;

class ProductManufacturerTranslationDetailCollection extends ProductManufacturerTranslationBasicCollection
{
    /**
     * @var ProductManufacturerTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getProductManufacturers(): ProductManufacturerBasicCollection
    {
        return new ProductManufacturerBasicCollection(
            $this->fmap(function (ProductManufacturerTranslationDetailStruct $productManufacturerTranslation) {
                return $productManufacturerTranslation->getProductManufacturer();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (ProductManufacturerTranslationDetailStruct $productManufacturerTranslation) {
                return $productManufacturerTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerTranslationDetailStruct::class;
    }
}
