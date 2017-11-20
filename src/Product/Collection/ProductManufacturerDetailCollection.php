<?php declare(strict_types=1);

namespace Shopware\Product\Collection;

use Shopware\Product\Struct\ProductManufacturerDetailStruct;

class ProductManufacturerDetailCollection extends ProductManufacturerBasicCollection
{
    /**
     * @var ProductManufacturerDetailStruct[]
     */
    protected $elements = [];

    public function getProductUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProducts()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProducts(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProducts()->getElements());
        }

        return $collection;
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): ProductManufacturerTranslationBasicCollection
    {
        $collection = new ProductManufacturerTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ProductManufacturerDetailStruct::class;
    }
}
