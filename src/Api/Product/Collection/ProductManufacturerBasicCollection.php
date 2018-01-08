<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Product\Struct\ProductManufacturerBasicStruct;

class ProductManufacturerBasicCollection extends EntityCollection
{
    /**
     * @var ProductManufacturerBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductManufacturerBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductManufacturerBasicStruct
    {
        return parent::current();
    }

    public function getMediaUuids(): array
    {
        return $this->fmap(function (ProductManufacturerBasicStruct $productManufacturer) {
            return $productManufacturer->getMediaUuid();
        });
    }

    public function filterByMediaUuid(string $uuid): self
    {
        return $this->filter(function (ProductManufacturerBasicStruct $productManufacturer) use ($uuid) {
            return $productManufacturer->getMediaUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerBasicStruct::class;
    }
}
