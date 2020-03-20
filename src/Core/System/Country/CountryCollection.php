<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(CountryEntity $entity)
 * @method void               set(string $key, CountryEntity $entity)
 * @method CountryEntity[]    getIterator()
 * @method CountryEntity[]    getElements()
 * @method CountryEntity|null get(string $key)
 * @method CountryEntity|null first()
 * @method CountryEntity|null last()
 */
class CountryCollection extends EntityCollection
{
    public function sortCountryAndStates(): void
    {
        $this->sortByPositionAndName();

        foreach ($this->getIterator() as $country) {
            if ($country->getStates()) {
                $country->getStates()->sortByPositionAndName();
            }
        }
    }

    public function sortByPositionAndName(): void
    {
        uasort($this->elements, function (CountryEntity $a, CountryEntity $b) {
            if ($a->getPosition() !== $b->getPosition()) {
                return $a->getPosition() <=> $b->getPosition();
            }

            if ($a->getName() !== $b->getName()) {
                return strnatcasecmp($a->getName(), $b->getName());
            }

            return 0;
        });
    }

    public function getApiAlias(): string
    {
        return 'country_collection';
    }

    protected function getExpectedClass(): string
    {
        return CountryEntity::class;
    }
}
