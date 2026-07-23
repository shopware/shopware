<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\PropertyGroupSorter;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PropertyGroupSorter::class)]
class PropertyGroupSorterTest extends TestCase
{
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $sorter = new PropertyGroupSorter();

        $this->expectException(DecorationPatternException::class);

        $sorter->getDecorated();
    }

    /**
     * @return \Generator<string, array{class-string<PropertyGroupOptionEntity|PartialEntity>, bool}>
     */
    public static function optionEntityTypeProvider(): \Generator
    {
        yield 'full entities' => [PropertyGroupOptionEntity::class, false];
        yield 'partial options, full groups' => [PartialEntity::class, false];
        yield 'fully partial' => [PartialEntity::class, true];
    }

    /**
     * @param class-string<PropertyGroupOptionEntity|PartialEntity> $entityType
     */
    #[DataProvider('optionEntityTypeProvider')]
    public function testSkipsInvisibleAndGroupLessOptionsAndGroupsByGroupId(string $entityType, bool $partialGroups): void
    {
        $visibleGroupId = Uuid::randomHex();
        $visibleGroup = $this->createGroup($visibleGroupId, PropertyGroupDefinition::SORTING_TYPE_POSITION, true, $partialGroups);

        $hiddenGroupId = Uuid::randomHex();
        $hiddenGroup = $this->createGroup($hiddenGroupId, PropertyGroupDefinition::SORTING_TYPE_POSITION, false, $partialGroups);

        $options = $this->createOptionsCollection(
            $this->createOption($entityType, $visibleGroupId, 'blue', 2, $visibleGroup),
            $this->createOption($entityType, $visibleGroupId, 'red', 1, $visibleGroup),
            $this->createOption($entityType, $hiddenGroupId, 'hidden', 1, $hiddenGroup),
            $this->createOption($entityType, Uuid::randomHex(), 'without-group', 1, null),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sortUsingLocaleCode($options, 'en-GB');

        static::assertCount(1, $result);

        $group = $result->first();
        static::assertNotNull($group);
        static::assertSame($visibleGroupId, $group->getId());

        $groupOptions = $group->get('options');
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $groupOptions);
        static::assertSame(['red', 'blue'], $this->extractOptionNames($groupOptions));
    }

    /**
     * @param class-string<PropertyGroupOptionEntity|PartialEntity> $entityType
     */
    #[DataProvider('optionEntityTypeProvider')]
    public function testUsesProvidedLocaleForPositionTiebreaker(string $entityType, bool $partialGroups): void
    {
        $groupId = Uuid::randomHex();
        $group = $this->createGroup($groupId, PropertyGroupDefinition::SORTING_TYPE_POSITION, true, $partialGroups);

        $options = $this->createOptionsCollection(
            $this->createOption($entityType, $groupId, 'b', 1, $group),
            $this->createOption($entityType, $groupId, 'ä', 1, $group),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sortUsingLocaleCode($options, 'de-DE');

        $sortedGroup = $result->first();
        static::assertNotNull($sortedGroup);

        $groupOptions = $sortedGroup->get('options');
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $groupOptions);
        static::assertSame(['ä', 'b'], $this->extractOptionNames($groupOptions));
    }

    /**
     * @param class-string<PropertyGroupOptionEntity|PartialEntity> $entityType
     */
    #[DataProvider('optionEntityTypeProvider')]
    public function testSortsAlphanumericallyWhenConfigured(string $entityType, bool $partialGroups): void
    {
        $groupId = Uuid::randomHex();
        $group = $this->createGroup($groupId, PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC, true, $partialGroups);

        $options = $this->createOptionsCollection(
            $this->createOption($entityType, $groupId, 'cherry', 3, $group),
            $this->createOption($entityType, $groupId, 'apple', 1, $group),
            $this->createOption($entityType, $groupId, 'banana', 2, $group),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sortUsingLocaleCode($options, 'en-GB');

        $sortedGroup = $result->first();
        static::assertNotNull($sortedGroup);

        $groupOptions = $sortedGroup->get('options');
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $groupOptions);
        static::assertSame(['apple', 'banana', 'cherry'], $this->extractOptionNames($groupOptions));
    }

    /**
     * @param class-string<PropertyGroupOptionEntity|PartialEntity> $entityType
     */
    #[DataProvider('optionEntityTypeProvider')]
    public function testGroupsMultipleVisibleGroups(string $entityType, bool $partialGroups): void
    {
        $groupAId = Uuid::randomHex();
        $groupBId = Uuid::randomHex();
        $groupA = $this->createGroup($groupAId, PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC, true, $partialGroups);
        $groupB = $this->createGroup($groupBId, PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC, true, $partialGroups);

        $options = $this->createOptionsCollection(
            $this->createOption($entityType, $groupAId, 'red', 1, $groupA),
            $this->createOption($entityType, $groupBId, 'large', 1, $groupB),
            $this->createOption($entityType, $groupAId, 'blue', 2, $groupA),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sortUsingLocaleCode($options, 'en-GB');

        static::assertCount(2, $result);

        $resultA = $result->get($groupAId);
        static::assertNotNull($resultA);
        $optionsA = $resultA->get('options');
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $optionsA);
        static::assertSame(['blue', 'red'], $this->extractOptionNames($optionsA));

        $resultB = $result->get($groupBId);
        static::assertNotNull($resultB);
        $optionsB = $resultB->get('options');
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $optionsB);
        static::assertSame(['large'], $this->extractOptionNames($optionsB));
    }

    /**
     * @param class-string<PropertyGroupOptionEntity|PartialEntity> $entityType
     */
    #[DataProvider('optionEntityTypeProvider')]
    public function testNormalizedOptionsExposeGroupIdWithoutReferencingTheirGroup(string $entityType, bool $partialGroups): void
    {
        $groupId = Uuid::randomHex();
        $group = $this->createGroup($groupId, PropertyGroupDefinition::SORTING_TYPE_POSITION, true, $partialGroups);

        $options = $this->createOptionsCollection(
            $this->createOption($entityType, $groupId, 'red', 1, $group),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sortUsingLocaleCode($options, 'en-GB');

        $sortedGroup = $result->first();
        static::assertNotNull($sortedGroup);

        $groupOptions = $sortedGroup->getOptions();
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $groupOptions);
        static::assertCount(1, $groupOptions);

        $normalizedOption = $groupOptions->first();
        static::assertNotNull($normalizedOption);
        static::assertSame($groupId, $normalizedOption->getGroupId());

        // The option must never point back to the group that already contains it. Such a
        // group <-> option cycle makes Entity::__clone() recurse infinitely, see
        // testSortedResultCanBeClonedWithoutInfiniteRecursion().
        static::assertNotSame($sortedGroup, $normalizedOption->getGroup());
    }

    /**
     * @param class-string<PropertyGroupOptionEntity|PartialEntity> $entityType
     */
    #[DataProvider('optionEntityTypeProvider')]
    public function testSortedResultCanBeClonedWithoutInfiniteRecursion(string $entityType, bool $partialGroups): void
    {
        $groupId = Uuid::randomHex();
        $group = $this->createGroup($groupId, PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC, true, $partialGroups);

        $options = $this->createOptionsCollection(
            $this->createOption($entityType, $groupId, 'red', 2, $group),
            $this->createOption($entityType, $groupId, 'blue', 1, $group),
        );

        $sorter = new PropertyGroupSorter();
        $result = $sorter->sortUsingLocaleCode($options, 'en-GB');

        // Regression guard for the storefront product detail page crash: the sorted collection is
        // attached to the product as `sortedProperties` and later cloned while the product graph is
        // copied. A cyclic group <-> option graph made Entity::__clone() (CloneTrait, no cycle
        // detection) recurse until the call stack overflowed. Cloning must terminate and copy the
        // options.
        $clonedResult = clone $result;

        $clonedGroup = $clonedResult->first();
        static::assertNotNull($clonedGroup);
        static::assertNotSame($result->first(), $clonedGroup);

        $clonedOptions = $clonedGroup->getOptions();
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $clonedOptions);
        static::assertSame(['blue', 'red'], $this->extractOptionNames($clonedOptions));
    }

    private function createGroup(string $id, string $sortingType, bool $visibleOnProductDetailPage, bool $partial = false): Entity
    {
        if ($partial) {
            $group = new PartialEntity();
            $group->assign([
                'id' => $id,
                'sortingType' => $sortingType,
                'displayType' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
                'visibleOnProductDetailPage' => $visibleOnProductDetailPage,
                'translated' => [
                    'name' => 'group-' . $id,
                    'position' => 1,
                ],
            ]);

            return $group;
        }

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

    /**
     * @param class-string<PropertyGroupOptionEntity|PartialEntity> $entityType
     */
    private function createOption(string $entityType, string $groupId, string $name, int $position, ?Entity $group): Entity
    {
        if ($entityType === PartialEntity::class) {
            return $this->createPartialOption($groupId, $name, $position, $group);
        }

        \assert($group instanceof PropertyGroupEntity || $group === null);

        return $this->createPropertyGroupOption($groupId, $name, $position, $group);
    }

    private function createPropertyGroupOption(string $groupId, string $name, int $position, ?PropertyGroupEntity $group): PropertyGroupOptionEntity
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

    private function createPartialOption(string $groupId, string $name, int $position, ?Entity $group): PartialEntity
    {
        $option = new PartialEntity();
        $option->assign([
            'id' => Uuid::randomHex(),
            'groupId' => $groupId,
            'name' => $name,
            'position' => $position,
            'group' => $group,
            'translated' => [
                'name' => $name,
                'position' => $position,
            ],
        ]);

        return $option;
    }

    /**
     * @return EntityCollection<PropertyGroupOptionEntity|PartialEntity>
     */
    private function createOptionsCollection(Entity ...$options): EntityCollection
    {
        $collection = new EntityCollection();

        $collection->fill($options);

        return $collection;
    }

    /**
     * @return list<string>
     */
    private function extractOptionNames(PropertyGroupOptionCollection $options): array
    {
        $names = [];
        foreach ($options as $option) {
            $names[] = (string) ($option->getTranslation('name') ?? $option->get('name') ?? '');
        }

        return $names;
    }
}
