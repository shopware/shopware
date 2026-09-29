<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Property;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PropertyGroupOptionCollection::class)]
#[CoversClass(PropertyGroupCollection::class)]
class PropertySortTest extends TestCase
{
    /**
     * @var array<string>
     */
    private array $notShuffledName = [];

    /**
     * @var array<string>
     */
    private array $notShuffledPosition = [];

    /**
     * Expected: [0,1,2,3,4,5,6...]
     */
    public function testAlphaNumericSortingNumbersOnly(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericOnlyNumbers();
        $propertyGroups->sortByConfig();
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        $equalsArray = [];
        for ($x = 0; $x < 50; ++$x) {
            $equalsArray[] = $x;
        }

        static::assertEquals(
            $equalsArray,
            array_column($propertyOptionsArray, 'name')
        );
    }

    /**
     * Expected: [0 => a, 1 => a, ... 48 => a, 49 => b, 98 => c]
     */
    public function testAlphaNumericSortingNumbersOnlyLetters(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericOnlyLetters();
        $propertyGroups->sortByConfig();
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        $equalsArray = [];
        $letterArray = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        for ($x = 0; $x < 10; ++$x) {
            $equalsArray[] = $letterArray[$x];
        }

        static::assertSame(
            $equalsArray,
            array_column($propertyOptionsArray, 'name')
        );
    }

    /**
     * Expected: [0 => 1, 1 => 1, 2 => 1, ... 9 => 1, .... 10 => 2, 11 => 2, ...488 => 49, 489 => 49]
     */
    public function testPositionSorting(): void
    {
        $propertyGroups = $this->getPropertyGroupPosition();
        $propertyGroups->sortByConfig();
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        $equalsArray = [];
        for ($x = 10; $x < 20; ++$x) {
            $equalsArray[] = $x;
        }

        static::assertSame(
            $equalsArray,
            array_column($propertyOptionsArray, 'position')
        );
    }

    /**
     * [0 => 0, 1 => 1, 2 => 2, ...10 => 10]
     */
    public function testPositionSortingMixed(): void
    {
        $propertyGroups = $this->getPropertyGroupPositionMixed();
        $propertyGroups->sortByConfig();
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            $this->notShuffledPosition,
            array_column($propertyOptionsArray, 'position')
        );
    }

    /**
     * [1a, 2aa, 3d, 3e, 3-x$e, 20aa, 44f, 55g, h6, i7, j2]
     */
    public function testAlphaNumericSortingMixed(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericMixed();
        $propertyGroups->sortByConfig();
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            $this->notShuffledName,
            array_column($propertyOptionsArray, 'name')
        );
    }

    public function testAlphaNumericSortingMixedCases(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericMixedCases();
        $propertyGroups->sortByConfig('de-DE');
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['1A', '2aa', '3D', '3e', '3-x$e', '20AA', '44f', '55g', 'a', 'A', 'ä', 'Ä', 'aa', 'Ab', 'b', 'B', 'h6', 'i7', 'j2', 'ö', 'Ö', 'ü', 'Ü'],
            array_column($propertyOptionsArray, 'name')
        );
    }

    public function testAlphaNumericSortingMixedCasesPositionFirst(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericMixedCasesPositionFirst();
        $propertyGroups->sortByConfig('de-DE');
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['a', 'b', 'A', 'B', '1A', '2aa', '20AA', '3D', '3e', '44f', '55g', 'h6', 'i7', 'j2', '3-x$e', 'Ab', 'aa', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'],
            array_column($propertyOptionsArray, 'name')
        );
    }

    public function testPositionSortingWithAlphanumericTiebreaker(): void
    {
        $propertyGroups = $this->getPropertyGroupPositionWithTiedPositions();
        $propertyGroups->sortByConfig('de-DE');
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['ä', 'b', 'ü', 'a', 'A', 'Ö', 'ö', 'z'],
            array_column($propertyOptionsArray, 'name')
        );
    }

    public function testPositionSortingAllSamePositionFallsBackToAlphanumeric(): void
    {
        $propertyGroups = $this->getPropertyGroupPositionAllSame();
        $propertyGroups->sortByConfig('de-DE');
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['a', 'ä', 'b', 'ö', 'ü', 'z'],
            array_column($propertyOptionsArray, 'name')
        );
    }

    public function testPositionSortingWithEmptyNamesAndNullPositions(): void
    {
        $propertyGroups = $this->getPropertyGroupPositionWithEmptyNames();
        $propertyGroups->sortByConfig('de-DE');
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            ['', '', 'a', 'ä', 'b'],
            array_column($propertyOptionsArray, 'name')
        );
    }

    public function testAlphaNumericSortingWorksWithoutLocaleParameter(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericMixedCases();
        $propertyGroups->sortByConfig();
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(23, $propertyOptionsArray);
    }

    public function testDeprecatedMethodSortByConfigThrowsException(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericMixedCases();
        static::expectException(FeatureException::class);
        $propertyGroups->sortByConfig(null);
    }

    private function getPropertyGroupAlphaNumericOnlyNumbers(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Alphanumeric only numbers');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsOnlyNumbers());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyGroupAlphaNumericOnlyLetters(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Alphanumeric only letters');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsOnlyLetters());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyGroupPosition(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Position');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsPosition());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyGroupPositionMixed(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Position');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsMixed());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyGroupAlphaNumericMixed(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Position');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsMixed());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyOptionsOnlyNumbers(): PropertyGroupOptionCollection
    {
        $propertyOptions = [];
        for ($x = 0; $x < 50; ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition(1);
            $propertyOption->setName((string) $x);
            $propertyOption->setTranslated([
                'name' => (string) $x,
                'description' => '',
                'position' => 1,
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }

    private function getPropertyOptionsOnlyLetters(): PropertyGroupOptionCollection
    {
        $propertyOptions = [];
        $letterArray = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        for ($x = 0; $x < 10; ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition(1);
            $propertyOption->setName($letterArray[$x % 10]);
            $propertyOption->setTranslated([
                'name' => $letterArray[$x],
                'description' => '',
                'position' => 1,
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }

    private function getPropertyOptionsPosition(): PropertyGroupOptionCollection
    {
        $propertyOptions = [];
        $letterArray = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        for ($x = 10; $x < 20; ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition($x);
            $name = $letterArray[array_rand($letterArray)];
            $propertyOption->setName($name);
            $propertyOption->setTranslated([
                'name' => $name,
                'description' => '',
                'position' => $x,
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }

    private function getPropertyOptionsMixed(): PropertyGroupOptionCollection
    {
        $propertyOptions = [];
        $letterArray = ['1a', '2aa', '20aa', '3d', '3e', '44f', '55g', 'h6', 'i7', 'j2', '3-x$e'];
        for ($x = 0; $x < 11; ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition($x);
            $name = $letterArray[$x];
            $propertyOption->setName($name);
            $propertyOption->setTranslated([
                'name' => $name,
                'description' => '',
                'position' => $x,
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        $this->notShuffledName = ['1a', '2aa', '3d', '3e', '3-x$e', '20aa', '44f', '55g', 'h6', 'i7', 'j2'];
        $this->notShuffledPosition = array_column(json_decode(json_encode($propertyOptions, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR), 'position');
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }

    private function getPropertyGroupAlphaNumericMixedCases(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('AlphaNumeric Mixed Cases');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsMixedCases());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyGroupAlphaNumericMixedCasesPositionFirst(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('AlphaNumeric Mixed Cases Position First');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsMixedCases());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyOptionsMixedCases(): PropertyGroupOptionCollection
    {
        $propertyOptions = [];
        $letterArray = ['a', 'b', 'A', 'B', '1A', '2aa', '20AA', '3D', '3e', '44f', '55g', 'h6', 'i7', 'j2', '3-x$e', 'Ab', 'aa', 'ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'];
        for ($x = 0; $x < \count($letterArray); ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition($x);
            $propertyOption->setName($letterArray[$x]);
            $propertyOption->setTranslated([
                'name' => $letterArray[$x],
                'description' => '',
                'position' => $x,
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }

    private function getPropertyGroupPositionWithTiedPositions(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Position with tied positions');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsWithTiedPositions());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    /**
     * @return PropertyGroupOptionCollection Options with duplicate positions to test alphanumeric tiebreaker:
     *                                       pos 1: ü, b, ä | pos 2: Ö, A, a | pos 3: z, ö
     */
    private function getPropertyOptionsWithTiedPositions(): PropertyGroupOptionCollection
    {
        $items = [
            ['name' => 'ü', 'position' => 1],
            ['name' => 'b', 'position' => 1],
            ['name' => 'ä', 'position' => 1],
            ['name' => 'Ö', 'position' => 2],
            ['name' => 'A', 'position' => 2],
            ['name' => 'a', 'position' => 2],
            ['name' => 'z', 'position' => 3],
            ['name' => 'ö', 'position' => 3],
        ];

        $propertyOptions = [];
        foreach ($items as $item) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition($item['position']);
            $propertyOption->setName($item['name']);
            $propertyOption->setTranslated([
                'name' => $item['name'],
                'description' => '',
                'position' => $item['position'],
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }

    private function getPropertyGroupPositionAllSame(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Position all same');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsAllSamePosition());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyOptionsAllSamePosition(): PropertyGroupOptionCollection
    {
        $names = ['ü', 'b', 'ä', 'z', 'ö', 'a'];

        $propertyOptions = [];
        foreach ($names as $name) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition(0);
            $propertyOption->setName($name);
            $propertyOption->setTranslated([
                'name' => $name,
                'description' => '',
                'position' => 0,
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }

    private function getPropertyGroupPositionWithEmptyNames(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Position with empty names');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_POSITION);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsWithEmptyNames());

        return new PropertyGroupCollection([$propertyGroup]);
    }

    private function getPropertyOptionsWithEmptyNames(): PropertyGroupOptionCollection
    {
        $items = [
            ['name' => 'b', 'position' => 0],
            ['name' => '', 'position' => 0],
            ['name' => 'ä', 'position' => 0],
            ['name' => 'a', 'position' => 0],
            ['name' => '', 'position' => 0],
        ];

        $propertyOptions = [];
        foreach ($items as $item) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition($item['position']);
            $propertyOption->setName($item['name']);
            $propertyOption->setTranslated([
                'name' => $item['name'],
                'description' => '',
                'position' => $item['position'],
                'customFields' => [],
            ]);
            $propertyOptions[] = $propertyOption;
        }
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }
}
