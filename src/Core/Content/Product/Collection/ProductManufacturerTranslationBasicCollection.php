<?php declare(strict_types=1);

namespace Shopware\Content\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Content\Product\Struct\ProductManufacturerTranslationBasicStruct;

class ProductManufacturerTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ProductManufacturerTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductManufacturerTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductManufacturerTranslationBasicStruct
    {
        return parent::current();
    }

    public function getProductManufacturerIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) {
            return $productManufacturerTranslation->getProductManufacturerId();
        });
    }

    public function filterByProductManufacturerId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getProductManufacturerId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) {
            return $productManufacturerTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerTranslationBasicStruct::class;
    }
}
