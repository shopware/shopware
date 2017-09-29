<?php declare(strict_types=1);

namespace Shopware\ProductVoteAverage\Struct;

use Shopware\Framework\Struct\Collection;

class ProductVoteAverageBasicCollection extends Collection
{
    /**
     * @var ProductVoteAverageBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductVoteAverageBasicStruct $productVoteAverage): void
    {
        $key = $this->getKey($productVoteAverage);
        $this->elements[$key] = $productVoteAverage;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductVoteAverageBasicStruct $productVoteAverage): void
    {
        parent::doRemoveByKey($this->getKey($productVoteAverage));
    }

    public function exists(ProductVoteAverageBasicStruct $productVoteAverage): bool
    {
        return parent::has($this->getKey($productVoteAverage));
    }

    public function getList(array $uuids): ProductVoteAverageBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductVoteAverageBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductVoteAverageBasicStruct $productVoteAverage) {
            return $productVoteAverage->getUuid();
        });
    }

    public function merge(ProductVoteAverageBasicCollection $collection)
    {
        /** @var ProductVoteAverageBasicStruct $productVoteAverage */
        foreach ($collection as $productVoteAverage) {
            if ($this->has($this->getKey($productVoteAverage))) {
                continue;
            }
            $this->add($productVoteAverage);
        }
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductVoteAverageBasicStruct $productVoteAverage) {
            return $productVoteAverage->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductVoteAverageBasicCollection
    {
        return $this->filter(function (ProductVoteAverageBasicStruct $productVoteAverage) use ($uuid) {
            return $productVoteAverage->getProductUuid() === $uuid;
        });
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ProductVoteAverageBasicStruct $productVoteAverage) {
            return $productVoteAverage->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ProductVoteAverageBasicCollection
    {
        return $this->filter(function (ProductVoteAverageBasicStruct $productVoteAverage) use ($uuid) {
            return $productVoteAverage->getShopUuid() === $uuid;
        });
    }

    protected function getKey(ProductVoteAverageBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
