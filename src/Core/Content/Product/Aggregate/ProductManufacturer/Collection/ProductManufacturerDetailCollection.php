<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Collection;

use Shopware\Core\Content\Media\Collection\MediaBasicCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Struct\ProductManufacturerDetailStruct;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Collection\ProductManufacturerTranslationBasicCollection;

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
