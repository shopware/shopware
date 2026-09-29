<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Property\Aggregate\PropertyGroupOption;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PropertyGroupOptionCollection::class)]
class PropertyGroupOptionCollectionTest extends TestCase
{
    public function testGroupByPropertyGroupsWithoutGroup(): void
    {
        $propertyGroupOptionEntity = new PropertyGroupOptionEntity();
        $propertyGroupOptionEntity->setId(Uuid::randomHex());

        $collection = new PropertyGroupOptionCollection([
            $propertyGroupOptionEntity,
        ]);

        static::assertCount(0, $collection->groupByPropertyGroups());
    }

    public function testGroupByPropertyGroupsWithGroup(): void
    {
        $propertyGroupEntity = new PropertyGroupEntity();
        $propertyGroupEntity->setId(Uuid::randomHex());

        $propertyGroupOptionEntity = new PropertyGroupOptionEntity();
        $propertyGroupOptionEntity->setId(Uuid::randomHex());
        $propertyGroupOptionEntity->setGroup($propertyGroupEntity);
        $propertyGroupOptionEntity->setGroupId($propertyGroupEntity->getId());

        $collection = new PropertyGroupOptionCollection([
            $propertyGroupOptionEntity,
        ]);

        $groupedCollection = $collection->groupByPropertyGroups();

        static::assertCount(1, $groupedCollection);
        $first = $groupedCollection->first();
        static::assertNotNull($first);
        static::assertSame($propertyGroupEntity->getId(), $first->getId());
        $options = $first->getOptions();
        static::assertNotNull($options);
        static::assertCount(1, $options);
        $firstOption = $options->first();
        static::assertNotNull($firstOption);
        static::assertSame($propertyGroupOptionEntity->getId(), $firstOption->getId());
    }

    public function testFiltersByGroupAndMediaId(): void
    {
        $red = $this->createOption('option-red', 'color-group', 'media-red');
        $blue = $this->createOption('option-blue', 'color-group', null);
        $large = $this->createOption('option-l', 'size-group', null);

        $collection = new PropertyGroupOptionCollection([$red, $blue, $large]);

        static::assertSame(['color-group', 'color-group', 'size-group'], array_values($collection->getPropertyGroupIds()));
        static::assertSame(['media-red'], array_values($collection->getMediaIds()));
        static::assertSame(['option-red', 'option-blue'], $collection->filterByGroupId('color-group')->getKeys());
        static::assertSame(['option-red'], $collection->filterByMediaId('media-red')->getKeys());
    }

    public function testGetGroupsCollectsTheAssignedGroups(): void
    {
        $collection = new PropertyGroupOptionCollection([
            $this->createOption('option-red', 'color-group', null),
            $this->createOption('option-l', 'size-group', null),
        ]);

        static::assertSame(['color-group', 'size-group'], $collection->getGroups()->getKeys());
    }

    private function createOption(string $id, string $groupId, ?string $mediaId): PropertyGroupOptionEntity
    {
        $group = new PropertyGroupEntity();
        $group->setId($groupId);

        $option = new PropertyGroupOptionEntity();
        $option->setId($id);
        $option->setGroupId($groupId);
        $option->setGroup($group);

        if ($mediaId !== null) {
            $option->setMediaId($mediaId);
        }

        return $option;
    }
}
