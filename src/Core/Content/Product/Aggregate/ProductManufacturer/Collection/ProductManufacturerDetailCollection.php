<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer\Collection;

use Shopware\Content\Media\Collection\MediaBasicCollection;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerDetailStruct;
use Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Collection\ProductManufacturerTranslationBasicCollection;

class ProductManufacturerDetailCollection extends ProductManufacturerBasicCollection
{
    /**
     * @var ProductManufacturerDetailStruct[]
     */
    protected $elements = [];

    public function getMedia(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (ProductManufacturerDetailStruct $productManufacturer) {
                return $productManufacturer->getMedia();
            })
        );
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
