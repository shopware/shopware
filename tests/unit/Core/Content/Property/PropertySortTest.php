<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Property;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
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

        static::assertEquals(
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

        static::assertEquals(
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

        static::assertEquals(
            $this->notShuffledPosition,
            array_column($propertyOptionsArray, 'position')
        );
    }

    /**
     * [1a, 2aa, 3-x$e, 3d, 3e, 20aa, 44f, 55g, h6, i7, j2]
     */
    public function testAlphaNumericSortingMixed(): void
    {
        $propertyGroups = $this->getPropertyGroupAlphaNumericMixed();
        $propertyGroups->sortByConfig();
        $propertyGroup = $propertyGroups->first();
        static::assertNotNull($propertyGroup);
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            $this->notShuffledName,
            array_column($propertyOptionsArray, 'name')
        );
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
            $propertyOption->setPosition((int) $x);
            $name = $letterArray[array_rand($letterArray)];
            $propertyOption->setName($name);
            $propertyOption->setTranslated([
                'name' => $name,
                'description' => '',
                'position' => (int) $x,
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
        $this->notShuffledName = ['1a', '2aa', '3-x$e', '3d', '3e', '20aa', '44f', '55g', 'h6', 'i7', 'j2'];
        $this->notShuffledPosition = array_column(json_decode(json_encode($propertyOptions, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR), 'position');
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }
}
