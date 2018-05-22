<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer\Collection;

use Shopware\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerBasicStruct;
use Shopware\Framework\ORM\EntityCollection;

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
