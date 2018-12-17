<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductManufacturerTranslationCollection extends EntityCollection
{
    /**
     * @var ProductManufacturerTranslationEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductManufacturerTranslationEntity
    {
        return parent::get($id);
    }

    public function current(): ProductManufacturerTranslationEntity
    {
        return parent::current();
    }

    public function getProductManufacturerIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) {
            return $productManufacturerTranslation->getProductManufacturerId();
        });
    }

    public function filterByProductManufacturerId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getProductManufacturerId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) {
            return $productManufacturerTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (ProductManufacturerTranslationEntity $productManufacturerTranslation) use ($id) {
            return $productManufacturerTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerTranslationEntity::class;
    }
}
