<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CountryBasicCollection extends EntityCollection
{
    /**
     * @var CountryBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CountryBasicStruct
    {
        return parent::current();
    }

    public function getAreaIds(): array
    {
        return $this->fmap(function (CountryBasicStruct $country) {
            return $country->getAreaId();
        });
    }

    public function filterByAreaId(string $id): self
    {
        return $this->filter(function (CountryBasicStruct $country) use ($id) {
            return $country->getAreaId() === $id;
        });
    }

    public function getTaxfreeForVatIds(): array
    {
        return $this->fmap(function (CountryBasicStruct $country) {
            return $country->getTaxfreeForVatId();
        });
    }

    public function filterByTaxfreeForVatId(string $id): self
    {
        return $this->filter(function (CountryBasicStruct $country) use ($id) {
            return $country->getTaxfreeForVatId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryBasicStruct::class;
    }
}
