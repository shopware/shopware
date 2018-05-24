<?php declare(strict_types=1);

namespace Shopware\System\Country\Aggregate\CountryState\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Country\Aggregate\CountryState\Struct\CountryStateBasicStruct;

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

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryStateBasicStruct $a, CountryStateBasicStruct $b) {
            if ($a->getPosition() !== $b->getPosition()) {
                return $a->getPosition() <=> $b->getPosition();
            }

            if ($a->getName() !== $b->getName()) {
                return strnatcasecmp($a->getName(), $b->getName());
            }

            return 0;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateBasicStruct::class;
    }
}
