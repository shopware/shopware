<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Property;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PropertyGroupCollection::class)]
class PropertyGroupCollectionTest extends TestCase
{
    public function testGetOptionIdMapMapsEveryOptionToItsGroup(): void
    {
        $collection = new PropertyGroupCollection([
            $this->createGroup('color', ['red', 'blue']),
            $this->createGroup('size', ['l']),
            $this->createGroup('empty', []),
        ]);

        static::assertSame([
            'red' => 'color',
            'blue' => 'color',
            'l' => 'size',
        ], $collection->getOptionIdMap());
    }

    public function testSortByPositionsOrdersByPositionThenNaturalName(): void
    {
        $second = $this->createGroup('second', []);
        $second->setTranslated(['position' => 2, 'name' => 'Alpha']);

        $firstB = $this->createGroup('first-b', []);
        $firstB->setTranslated(['position' => 1, 'name' => 'Group 10']);

        $firstA = $this->createGroup('first-a', []);
        $firstA->setTranslated(['position' => 1, 'name' => 'Group 2']);

        $collection = new PropertyGroupCollection([$second, $firstB, $firstA]);
        $collection->sortByPositions();

        static::assertSame(['first-a', 'first-b', 'second'], array_keys($collection->getElements()));
    }

    /**
     * @param list<string> $optionIds
     */
    private function createGroup(string $id, array $optionIds): PropertyGroupEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($id);

        if ($optionIds !== []) {
            $options = new PropertyGroupOptionCollection();
            foreach ($optionIds as $optionId) {
                $option = new PropertyGroupOptionEntity();
                $option->setId($optionId);
                $options->add($option);
            }
            $group->setOptions($options);
        }

        return $group;
    }
}
