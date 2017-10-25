<?php declare(strict_types=1);

namespace Shopware\ProductVote\Struct;

use Shopware\Framework\Struct\Collection;

class ProductVoteBasicCollection extends Collection
{
    /**
     * @var ProductVoteBasicStruct[]
     */
    protected $elements = [];

    public function add(ProductVoteBasicStruct $productVote): void
    {
        $key = $this->getKey($productVote);
        $this->elements[$key] = $productVote;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(ProductVoteBasicStruct $productVote): void
    {
        parent::doRemoveByKey($this->getKey($productVote));
    }

    public function exists(ProductVoteBasicStruct $productVote): bool
    {
        return parent::has($this->getKey($productVote));
    }

    public function getList(array $uuids): ProductVoteBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? ProductVoteBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (ProductVoteBasicStruct $productVote) {
            return $productVote->getUuid();
        });
    }

    public function merge(ProductVoteBasicCollection $collection)
    {
        /** @var ProductVoteBasicStruct $productVote */
        foreach ($collection as $productVote) {
            if ($this->has($this->getKey($productVote))) {
                continue;
            }
            $this->add($productVote);
        }
    }

    public function getProductUuids(): array
    {
        return $this->fmap(function (ProductVoteBasicStruct $productVote) {
            return $productVote->getProductUuid();
        });
    }

    public function filterByProductUuid(string $uuid): ProductVoteBasicCollection
    {
        return $this->filter(function (ProductVoteBasicStruct $productVote) use ($uuid) {
            return $productVote->getProductUuid() === $uuid;
        });
    }

    public function getShopUuids(): array
    {
        return $this->fmap(function (ProductVoteBasicStruct $productVote) {
            return $productVote->getShopUuid();
        });
    }

    public function filterByShopUuid(string $uuid): ProductVoteBasicCollection
    {
        return $this->filter(function (ProductVoteBasicStruct $productVote) use ($uuid) {
            return $productVote->getShopUuid() === $uuid;
        });
    }

    public function current(): ProductVoteBasicStruct
    {
        return parent::current();
    }

    protected function getKey(ProductVoteBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
