<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\PropertyGroupSorter;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(PropertyGroupSorter::class)]
class PropertyGroupSorterTest extends TestCase
{
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $sorter = new PropertyGroupSorter();

        $this->expectException(DecorationPatternException::class);

        $sorter->getDecorated();
    }

    public function testSortSkipsInvisibleAndGroupLessOptionsAndGroupsByGroupId(): void
    {
        $visibleGroupId = Uuid::randomHex();
        $visibleGroup = $this->createGroup($visibleGroupId, PropertyGroupDefinition::SORTING_TYPE_POSITION, true);

        $hiddenGroup = $this->createGroup(Uuid::randomHex(), PropertyGroupDefinition::SORTING_TYPE_POSITION, false);

        $options = $this->createOptionsCollection(
            $this->createOption($visibleGroupId, 'blue', 2, $visibleGroup),
            $this->createOption($visibleGroupId, 'red', 1, $visibleGroup),
            $this->createOption($hiddenGroup->getId(), 'hidden', 1, $hiddenGroup),
            $this->createOption(Uuid::randomHex(), 'without-group', 1, null),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sort($options);

        static::assertCount(1, $result);

        $group = $result->first();
        static::assertNotNull($group);
        static::assertSame($visibleGroupId, $group->getId());
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $group->getOptions());
        static::assertSame(['red', 'blue'], $this->extractOptionNames($group->getOptions()));
    }

    public function testSortUsesProvidedLocaleForPositionTiebreaker(): void
    {
        $groupId = Uuid::randomHex();
        $group = $this->createGroup($groupId, PropertyGroupDefinition::SORTING_TYPE_POSITION, true);

        $options = $this->createOptionsCollection(
            $this->createOption($groupId, 'b', 1, $group),
            $this->createOption($groupId, 'ä', 1, $group),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sort(...[$options, 'de-DE']);

        $sortedGroup = $result->first();
        static::assertNotNull($sortedGroup);
        static::assertNotNull($sortedGroup->getOptions());
        static::assertSame(['ä', 'b'], $this->extractOptionNames($sortedGroup->getOptions()));
    }

    private function createGroup(string $id, string $sortingType, bool $visibleOnProductDetailPage): PropertyGroupEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($id);
        $group->setSortingType($sortingType);
        $group->setDisplayType(PropertyGroupDefinition::DISPLAY_TYPE_TEXT);
        $group->setTranslated([
            'name' => 'group-' . $id,
            'position' => 1,
        ]);
        $group->assign([
            'visibleOnProductDetailPage' => $visibleOnProductDetailPage,
        ]);

        return $group;
    }

    private function createOption(string $groupId, string $name, int $position, ?PropertyGroupEntity $group): PropertyGroupOptionEntity
    {
        $option = new PropertyGroupOptionEntity();
        $option->setId(Uuid::randomHex());
        $option->setGroupId($groupId);
        $option->setName($name);
        $option->setPosition($position);
        $option->setGroup($group);
        $option->setTranslated([
            'name' => $name,
            'position' => $position,
        ]);

        return $option;
    }

    /**
     * @return EntityCollection<PropertyGroupOptionEntity|PartialEntity>
     */
    private function createOptionsCollection(PropertyGroupOptionEntity ...$options): EntityCollection
    {
        /** @var EntityCollection<PropertyGroupOptionEntity|PartialEntity> $collection */
        $collection = new EntityCollection();

        foreach ($options as $option) {
            $collection->add($option);
        }

        return $collection;
    }

    /**
     * @return list<string>
     */
    private function extractOptionNames(PropertyGroupOptionCollection $options): array
    {
        $names = [];
        foreach ($options as $option) {
            $names[] = (string) ($option->getTranslation('name') ?? $option->getName() ?? '');
        }

        return $names;
    }
}
