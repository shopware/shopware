<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryState;

use Shopware\Core\Framework\ORM\EntityCollection;

class CountryStateCollection extends EntityCollection
{
    /**
     * @var CountryStateStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? CountryStateStruct
    {
        return parent::get($id);
    }

    public function current(): CountryStateStruct
    {
        return parent::current();
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (CountryStateStruct $countryState) {
            return $countryState->getCountryId();
        });
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(function (CountryStateStruct $countryState) use ($id) {
            return $countryState->getCountryId() === $id;
        });
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryStateStruct $a, CountryStateStruct $b) {
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
        return CountryStateStruct::class;
    }
}
