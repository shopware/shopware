<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer;


use Shopware\Core\Framework\ORM\EntityCollection;

class ProductManufacturerCollection extends EntityCollection
{
    /**
     * @var ProductManufacturerStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ProductManufacturerStruct
    {
        return parent::get($id);
    }

    public function current(): ProductManufacturerStruct
    {
        return parent::current();
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (ProductManufacturerStruct $productManufacturer) {
            return $productManufacturer->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (ProductManufacturerStruct $productManufacturer) use ($id) {
            return $productManufacturer->getMediaId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerStruct::class;
    }
}
