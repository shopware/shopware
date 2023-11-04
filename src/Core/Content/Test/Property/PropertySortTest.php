<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Property;

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
class PropertySortTest extends TestCase
{
    /**
     * @var array<string>
     */
    private static array $notShuffledName = [];

    /**
     * @var array<string>
     */
    private static array $notShuffledPosition = [];

    /**
     * Expected: [0,1,2,3,4,5,6...]
     */
    public function testAlphaNumericSortingNumbersOnly(): void
    {
        $propertyGroup = $this->getPropertyGroupAlphaNumericOnlyNumbers();
        $propertyGroup->sortByConfig();
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->first()->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

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
        $propertyGroup = $this->getPropertyGroupAlphaNumericOnlyLetters();
        $propertyGroup->sortByConfig();
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->first()->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        $equalsArray = [];
        $letterArray = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        for ($x = 0; $x < 490; ++$x) {
            $equalsArray[] = $letterArray[(int) ($x / 49)];
        }

        static::assertEquals(
            $equalsArray,
            array_column($propertyOptionsArray, 'name')
        );
    }

    /**
     * Expected: [0 => 1a, 1 => 1b, ... 50 => 6a, 60 => 7a, 297 => 30h, 489 => 49j]
     */
    public function testAlphaNumericSortingNumbersFirstThenLetters(): void
    {
        $propertyGroup = $this->getPropertyGroupAlphaNumericNumbersFirstThenLetters();
        $propertyGroup->sortByConfig();
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->first()->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        $equalsArray = [];
        $letterArray = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        for ($x = 10; $x < 500; ++$x) {
            $equalsArray[] = (string) ((int) ($x / 10)) . $letterArray[$x % 10];
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
        $propertyGroup = $this->getPropertyGroupPosition();
        $propertyGroup->sortByConfig();
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->first()->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        $equalsArray = [];
        for ($x = 10; $x < 500; ++$x) {
            $equalsArray[] = (int) ($x / 10);
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
        $propertyGroup = $this->getPropertyGroupPositionMixed();
        $propertyGroup->sortByConfig();
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->first()->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            self::$notShuffledPosition,
            array_column($propertyOptionsArray, 'position')
        );
    }

    /**
     * [1a, 2aa, 3-x$e, 3d, 3e, 20aa, 44f, 55g, h6, i7, j2]
     */
    public function testAlphaNumericSortingMixed(): void
    {
        $propertyGroup = $this->getPropertyGroupAlphaNumericMixed();
        $propertyGroup->sortByConfig();
        $propertyOptionsArray = json_decode(json_encode($propertyGroup->first()->getOptions(), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(
            self::$notShuffledName,
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

    private function getPropertyGroupAlphaNumericNumbersFirstThenLetters(): PropertyGroupCollection
    {
        $propertyGroup = new PropertyGroupEntity();
        $propertyGroup->setId(Uuid::randomHex());
        $propertyGroup->setName('Alphanumeric numbers first then letters');
        $propertyGroup->setSortingType(PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC);
        $propertyGroup->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $propertyGroup->setPosition(1);
        $propertyGroup->setOptions($this->getPropertyOptionsNumbersFirstThenLetters());

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

    private function getPropertyOptionsNumbersFirstThenLetters(): PropertyGroupOptionCollection
    {
        $propertyOptions = [];
        $letterArray = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];
        for ($x = 10; $x < 500; ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition(1);
            $propertyOption->setName((string) ((int) ($x / 10)) . $letterArray[$x % 10]);
            $propertyOption->setTranslated([
                'name' => (string) ((int) ($x / 10)) . $letterArray[$x % 10],
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
        for ($x = 10; $x < 500; ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition(1);
            $propertyOption->setName($letterArray[$x % 10]);
            $propertyOption->setTranslated([
                'name' => $letterArray[$x % 10],
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
        for ($x = 10; $x < 500; ++$x) {
            $propertyOption = new PropertyGroupOptionEntity();
            $propertyOption->setId(Uuid::randomHex());
            $propertyOption->setPosition((int) ($x / 10));
            $name = $letterArray[array_rand($letterArray)];
            $propertyOption->setName($name);
            $propertyOption->setTranslated([
                'name' => $name,
                'description' => '',
                'position' => (int) ($x / 10),
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
        self::$notShuffledName = ['1a', '2aa', '3-x$e', '3d', '3e', '20aa', '44f', '55g', 'h6', 'i7', 'j2'];
        self::$notShuffledPosition = array_column(json_decode(json_encode($propertyOptions, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR), 'position');
        shuffle($propertyOptions);

        return new PropertyGroupOptionCollection($propertyOptions);
    }
}
