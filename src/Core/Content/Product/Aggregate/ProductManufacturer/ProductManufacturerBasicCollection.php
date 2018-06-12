<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

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
