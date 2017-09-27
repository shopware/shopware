<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Struct;

use Shopware\Framework\Struct\Collection;
use Shopware\Media\Struct\MediaBasicCollection;

class ProductMediaBasicCollection extends Collection
{
    /**
     * @var ProductMediaBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductMediaBasicStruct $productMedia): void
    {
        $key = $this->getKey($productMedia);
        $this->elements[$key] = $productMedia;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductMediaBasicStruct $productMedia): void
    {
        parent::doRemoveByKey($this->getKey($productMedia));
    }

    public function exists(ProductMediaBasicStruct $productMedia): bool
    {
        return parent::has($this->getKey($productMedia));
    }

    public function getList(array $uuids): ProductMediaBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductMediaBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductMediaBasicStruct $productMedia) {
            return $productMedia->getUuid();
        });
    }

    public function merge(ProductMediaBasicCollection $collection)
    {
        /** @var ProductMediaBasicStruct $productMedia */
        foreach ($collection as $productMedia) {
            if ($this->has($this->getKey($productMedia))) {
                continue;
            }
            $this->add($productMedia);
        }
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

    public function getProductDetailUuids(): array
    {
        return $this->fmap(function (ProductMediaBasicStruct $productMedia) {
            return $productMedia->getProductDetailUuid();
        });
    }

    public function filterByProductDetailUuid(string $uuid): ProductMediaBasicCollection
    {
        return $this->filter(function (ProductMediaBasicStruct $productMedia) use ($uuid) {
            return $productMedia->getProductDetailUuid() === $uuid;
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

    protected function getKey(ProductMediaBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
