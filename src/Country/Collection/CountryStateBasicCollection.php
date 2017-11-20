<?php declare(strict_types=1);

namespace Shopware\Country\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Country\Struct\CountryStateBasicStruct;

class CountryStateBasicCollection extends EntityCollection
{
    /**
     * @var CountryStateBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? CountryStateBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CountryStateBasicStruct
    {
        return parent::current();
    }

    public function getCountryUuids(): array
    {
        return $this->fmap(function (CountryStateBasicStruct $countryState) {
            return $countryState->getCountryUuid();
        });
    }

    public function filterByCountryUuid(string $uuid): CountryStateBasicCollection
    {
        return $this->filter(function (CountryStateBasicStruct $countryState) use ($uuid) {
            return $countryState->getCountryUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateBasicStruct::class;
    }
}
