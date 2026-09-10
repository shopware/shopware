<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryCollection::class)]
class CountryCollectionTest extends TestCase
{
    public function testSortByPositionAndNameThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Use sorting via SQL instead of this method.'
        ));

        (new CountryCollection())->sortByPositionAndName();
    }

    public function testSortCountryAndStatesThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Use sorting via SQL instead of this method.'
        ));

        (new CountryCollection())->sortCountryAndStates();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSortByPositionAndNameOrdersByPositionThenName(): void
    {
        $collection = new CountryCollection([
            self::country('zeta', 2, 'Zeta'),
            self::country('beta', 1, 'Beta'),
            self::country('alpha', 1, 'Alpha'),
        ]);

        $collection->sortByPositionAndName();

        static::assertSame(['alpha', 'beta', 'zeta'], array_keys($collection->getElements()));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSortCountryAndStatesAlsoSortsTheStates(): void
    {
        $second = new CountryStateEntity();
        $second->setUniqueIdentifier('second');
        $second->setId('second');
        $second->setPosition(2);
        $second->setTranslated(['name' => 'Second']);

        $first = new CountryStateEntity();
        $first->setUniqueIdentifier('first');
        $first->setId('first');
        $first->setPosition(1);
        $first->setTranslated(['name' => 'First']);

        $country = self::country('country', 1, 'Country');
        $country->setStates(new CountryStateCollection([$second, $first]));

        $collection = new CountryCollection([$country]);
        $collection->sortCountryAndStates();

        $states = $country->getStates();
        static::assertNotNull($states);
        static::assertSame(['first', 'second'], array_keys($states->getElements()));
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('country_collection', (new CountryCollection())->getApiAlias());
    }

    private static function country(string $id, int $position, string $name): CountryEntity
    {
        $country = new CountryEntity();
        $country->setUniqueIdentifier($id);
        $country->setId($id);
        $country->setPosition($position);
        $country->setTranslated(['name' => $name]);

        return $country;
    }
}
