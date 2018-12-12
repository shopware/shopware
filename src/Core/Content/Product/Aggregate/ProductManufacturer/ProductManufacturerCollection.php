<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ProductManufacturerCollection extends EntityCollection
{
    /**
     * @var ProductManufacturerEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductManufacturerEntity
    {
        return parent::get($id);
    }

    public function current(): ProductManufacturerEntity
    {
        return parent::current();
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (ProductManufacturerEntity $productManufacturer) {
            return $productManufacturer->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ProductManufacturerEntity $productManufacturer) use ($id) {
            return $productManufacturer->getMediaId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerEntity::class;
    }
}
