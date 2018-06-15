<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Collection;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Struct\ProductManufacturerTranslationDetailStruct;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;

class ProductManufacturerTranslationDetailCollection extends ProductManufacturerTranslationBasicCollection
{
    /**
     * @var \Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Struct\ProductManufacturerTranslationDetailStruct[]
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
