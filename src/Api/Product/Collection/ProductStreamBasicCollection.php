<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Listing\Collection\ListingSortingBasicCollection;
use Shopware\Api\Product\Struct\ProductStreamBasicStruct;

class ProductStreamBasicCollection extends EntityCollection
{
    /**
     * @var ProductStreamBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductStreamBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductStreamBasicStruct
    {
        return parent::current();
    }

    public function getListingSortingUuids(): array
    {
        return $this->fmap(function (ProductStreamBasicStruct $productStream) {
            return $productStream->getListingSortingUuid();
        });
    }

    public function filterByListingSortingUuid(string $uuid): ProductStreamBasicCollection
    {
        return $this->filter(function (ProductStreamBasicStruct $productStream) use ($uuid) {
            return $productStream->getListingSortingUuid() === $uuid;
        });
    }

    public function getListingSortings(): ListingSortingBasicCollection
    {
        return new ListingSortingBasicCollection(
            $this->fmap(function (ProductStreamBasicStruct $productStream) {
                return $productStream->getListingSorting();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductStreamBasicStruct::class;
    }
}
