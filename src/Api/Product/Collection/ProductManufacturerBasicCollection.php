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

    public function get(string $id): ? ProductManufacturerBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ProductManufacturerBasicStruct
    {
        return parent::current();
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (ProductManufacturerBasicStruct $productManufacturer) {
            return $productManufacturer->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ProductManufacturerBasicStruct $productManufacturer) use ($id) {
            return $productManufacturer->getMediaId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerBasicStruct::class;
    }
}
