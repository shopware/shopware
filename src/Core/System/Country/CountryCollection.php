<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CountryEntity>
 */
#[Package('fundamentals@discovery')]
class CountryCollection extends EntityCollection
{
    /**
     * @deprecated tag:v6.8.0 - will be removed, use sorting via SQL instead
     *
     * @phpstan-ignore-next-line shopware.deprecatedClass - Deprecations for 6.8.0.0 should only be soft
     */
    public function sortCountryAndStates(): void
    {
        $this->sortByPositionAndName();

        foreach ($this->getIterator() as $country) {
            if ($country->getStates()) {
                $country->getStates()->sortByPositionAndName();
            }
        }
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, use sorting via SQL instead
     *
     * @phpstan-ignore-next-line shopware.deprecatedClass - Deprecations for 6.8.0.0 should only be soft
     */
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
