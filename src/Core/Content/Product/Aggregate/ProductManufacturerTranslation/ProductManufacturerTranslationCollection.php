<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductManufacturerTranslationCollection extends EntityCollection
{
    /**
     * @var ProductManufacturerTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductManufacturerTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): ProductManufacturerTranslationStruct
    {
        return parent::current();
    }

    public function getProductManufacturerIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationStruct $productManufacturerTranslation) {
            return $productManufacturerTranslation->getProductManufacturerId();
        });
    }

    public function filterByProductManufacturerId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationStruct $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getProductManufacturerId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationStruct $productManufacturerTranslation) {
            return $productManufacturerTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationStruct $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerTranslationStruct::class;
    }
}
