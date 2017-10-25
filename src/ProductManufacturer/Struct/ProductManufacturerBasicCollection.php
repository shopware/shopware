<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Struct;

use Shopware\Framework\Struct\Collection;

class ProductManufacturerBasicCollection extends Collection
{
    /**
     * @var ProductManufacturerBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductManufacturerBasicStruct $productManufacturer): void
    {
        $key = $this->getKey($productManufacturer);
        $this->elements[$key] = $productManufacturer;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductManufacturerBasicStruct $productManufacturer): void
    {
        parent::doRemoveByKey($this->getKey($productManufacturer));
    }

    public function exists(ProductManufacturerBasicStruct $productManufacturer): bool
    {
        return parent::has($this->getKey($productManufacturer));
    }

    public function getList(array $uuids): ProductManufacturerBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductManufacturerBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductManufacturerBasicStruct $productManufacturer) {
            return $productManufacturer->getUuid();
        });
    }

    public function merge(ProductManufacturerBasicCollection $collection)
    {
        /** @var ProductManufacturerBasicStruct $productManufacturer */
        foreach ($collection as $productManufacturer) {
            if ($this->has($this->getKey($productManufacturer))) {
                continue;
            }
            $this->add($productManufacturer);
        }
    }

    public function getMediaUuids(): array
    {
        return $this->fmap(function (ProductManufacturerBasicStruct $productManufacturer) {
            return $productManufacturer->getMediaUuid();
        });
    }

    public function filterByMediaUuid(string $uuid): ProductManufacturerBasicCollection
    {
        return $this->filter(function (ProductManufacturerBasicStruct $productManufacturer) use ($uuid) {
            return $productManufacturer->getMediaUuid() === $uuid;
        });
    }

    public function current(): ProductManufacturerBasicStruct
    {
        return parent::current();
    }

    protected function getKey(ProductManufacturerBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
