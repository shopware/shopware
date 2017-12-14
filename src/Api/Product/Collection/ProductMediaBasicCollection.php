<?php declare(strict_types=1);

namespace Shopware\Api\Product\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Media\Collection\MediaBasicCollection;
use Shopware\Api\Product\Struct\ProductMediaBasicStruct;

class ProductMediaBasicCollection extends EntityCollection
{
    /**
     * @var ProductMediaBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? ProductMediaBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): ProductMediaBasicStruct
    {
        return parent::current();
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductMediaBasicStruct $productMedia) {
            return $productMedia->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductMediaBasicCollection
    {
        return $this->filter(function (ProductMediaBasicStruct $productMedia) use ($uuid) {
            return $productMedia->getProductUuid() === $uuid;
        });
    }

    public function getMediaUuids(): array
    {
        return $this->fmap(function (ProductMediaBasicStruct $productMedia) {
            return $productMedia->getMediaUuid();
        });
    }

    public function filterByMediaUuid(string $uuid): ProductMediaBasicCollection
    {
        return $this->filter(function (ProductMediaBasicStruct $productMedia) use ($uuid) {
            return $productMedia->getMediaUuid() === $uuid;
        });
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (ProductMediaBasicStruct $productMedia) {
            return $productMedia->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): ProductMediaBasicCollection
    {
        return $this->filter(function (ProductMediaBasicStruct $productMedia) use ($uuid) {
            return $productMedia->getParentUuid() === $uuid;
        });
    }

    public function getMedia(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (ProductMediaBasicStruct $productMedia) {
                return $productMedia->getMedia();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ProductMediaBasicStruct::class;
    }
}
