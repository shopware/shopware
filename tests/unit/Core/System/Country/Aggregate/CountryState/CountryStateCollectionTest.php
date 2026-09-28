<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Country\Aggregate\CountryState;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryStateCollection::class)]
class CountryStateCollectionTest extends TestCase
{
    public function testGetCountryIds(): void
    {
        $collection = new CountryStateCollection([
            self::state('bavaria', 'country-de', 1),
            self::state('tyrol', 'country-at', 2),
        ]);

        static::assertSame(
            ['bavaria' => 'country-de', 'tyrol' => 'country-at'],
            $collection->getCountryIds()
        );
    }

    public function testFilterByCountryId(): void
    {
        $match = self::state('bavaria', 'country-de', 1);

        $collection = new CountryStateCollection([$match, self::state('tyrol', 'country-at', 2)]);
        $filtered = $collection->filterByCountryId('country-de');

        static::assertSame([$match], array_values($filtered->getElements()));
    }

    public function testSortByPositionAndNameThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Use sorting via SQL instead of this method.'
        ));

        (new CountryStateCollection())->sortByPositionAndName();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSortByPositionAndNameOrdersByPositionThenName(): void
    {
        $collection = new CountryStateCollection([
            self::state('zealand', 'country', 2, 'Zealand'),
            self::state('bavaria-b', 'country', 1, 'Bergisch'),
            self::state('bavaria-a', 'country', 1, 'Ansbach'),
        ]);

        $collection->sortByPositionAndName();

        static::assertSame(['bavaria-a', 'bavaria-b', 'zealand'], array_keys($collection->getElements()));
    }

    public function testGetApiAlias(): void
    {
        static::assertSame('country_state_collection', (new CountryStateCollection())->getApiAlias());
    }

    private static function state(string $id, string $countryId, int $position, string $name = ''): CountryStateEntity
    {
        $state = new CountryStateEntity();
        $state->setUniqueIdentifier($id);
        $state->setId($id);
        $state->setCountryId($countryId);
        $state->setPosition($position);
        $state->setTranslated(['name' => $name]);

        return $state;
    }
}
