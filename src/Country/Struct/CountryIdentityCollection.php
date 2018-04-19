<?php

namespace Shopware\Country\Struct;

use Shopware\Framework\Struct\Collection;

class CountryIdentityCollection extends Collection
{
    /**
     * @var CountryIdentity[]
     */
    protected $elements = [];

    public function add(CountryIdentity $countryIdentity): void
    {
        $key = $this->getKey($countryIdentity);
        $this->elements[$key] = $countryIdentity;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(CountryIdentity $countryIdentity): void
    {
        parent::doRemoveByKey($this->getKey($countryIdentity));
    }

    public function exists(CountryIdentity $countryIdentity): bool
    {
        return parent::has($this->getKey($countryIdentity));
    }

    public function get(int $id): ? CountryIdentity
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getIds(): array
    {
        return $this->map(function(CountryIdentity $countryIdentity) {
            return $countryIdentity->getId();
        });
    }

    protected function getKey(CountryIdentity $element): int
    {
        return $element->getId();
    }

    public function getAreaIds(): array
    {
        return $this->fmap(function(CountryIdentity $countryIdentity) {
            return $countryIdentity->getAreaId();
        });
    }
}