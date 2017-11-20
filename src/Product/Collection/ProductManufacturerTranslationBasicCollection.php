<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Product\Struct\ProductManufacturerTranslationBasicStruct;

class ProductManufacturerTranslationBasicCollection extends EntityCollection
{
    /**
     * @var ProductManufacturerTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductManufacturerTranslationBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductManufacturerTranslationBasicStruct
    {
        return parent::current();
    }

    public function getProductManufacturerUuids(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) {
            return $productManufacturerTranslation->getProductManufacturerUuid();
        });
    }

    public function filterByProductManufacturerUuid(string $uuid): ProductManufacturerTranslationBasicCollection
    {
        return $this->filter(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) use ($uuid) {
            return $productManufacturerTranslation->getProductManufacturerUuid() === $uuid;
        });
    }

    public function getLanguageUuids(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) {
            return $productManufacturerTranslation->getLanguageUuid();
        });
    }

    public function filterByLanguageUuid(string $uuid): ProductManufacturerTranslationBasicCollection
    {
        return $this->filter(function (ProductManufacturerTranslationBasicStruct $productManufacturerTranslation) use ($uuid) {
            return $productManufacturerTranslation->getLanguageUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerTranslationBasicStruct::class;
    }
}
