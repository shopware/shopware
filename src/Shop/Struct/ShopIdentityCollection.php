<?php

namespace Shopware\Shop\Struct;

use Shopware\Framework\Struct\Collection;

class ShopIdentityCollection extends Collection
{
    /**
     * @var ShopIdentity[]
     */
    protected $shopIdentitys = [];

    public function add(ShopIdentity $shopIdentity): void
    {
        $key = $this->getKey($shopIdentity);
        $this->elements[$key] = $shopIdentity;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(ShopIdentity $shopIdentity): void
    {
        parent::doRemoveByKey($this->getKey($shopIdentity));
    }

    public function exists(ShopIdentity $shopIdentity): bool
    {
        return parent::has($this->getKey($shopIdentity));
    }

    public function get(int $id): ? ShopIdentity
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getIds(): array
    {
        return $this->map(function (ShopIdentity $shopIdentity) {
            return $shopIdentity->getId();
        });
    }

    protected function getKey(ShopIdentity $shopIdentity): int
    {
        return $shopIdentity->getId();
    }

    public function sortByPosition(): ShopIdentityCollection
    {
        $this->sort(function(ShopIdentity $a, ShopIdentity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });
        return $this;
    }
}