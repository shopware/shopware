<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Product\Struct\ProductManufacturerTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

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

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
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
