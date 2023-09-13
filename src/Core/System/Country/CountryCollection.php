<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryEntity>
 */
#[Package('core')]
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
        uasort($this->elements, static function (CountryEntity $a, CountryEntity $b) {
            $aPosition = $a->getPosition();
            $bPosition = $b->getPosition();

            if ($aPosition !== $bPosition) {
                return $aPosition <=> $bPosition;
            }

            $aName = (string) $a->getTranslation('name');
            $bName = (string) $b->getTranslation('name');
            if ($aName !== $bName) {
                return strnatcasecmp($aName, $bName);
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
