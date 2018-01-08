<?php declare(strict_types=1);

namespace Shopware\Api\Country\Collection;

use Shopware\Api\Country\Struct\CountryStateBasicStruct;
use Shopware\Api\Entity\EntityCollection;

class CountryStateBasicCollection extends EntityCollection
{
    /**
     * @var CountryStateBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryStateBasicStruct
    {
        return parent::get($id);
    }

    public function current(): CountryStateBasicStruct
    {
        return parent::current();
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CountryStateBasicStruct $countryState) {
            return $countryState->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CountryStateBasicStruct $countryState) use ($id) {
            return $countryState->getCountryId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateBasicStruct::class;
    }
}
