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

    public function get(string $uuid): ? CountryBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): CountryBasicStruct
    {
        return parent::current();
    }

    public function getAreaUuids(): array
    {
        return $this->fmap(function (CountryBasicStruct $country) {
            return $country->getAreaUuid();
        });
    }

    public function filterByAreaUuid(string $uuid): CountryBasicCollection
    {
        return $this->filter(function (CountryBasicStruct $country) use ($uuid) {
            return $country->getAreaUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryBasicStruct::class;
    }
}
