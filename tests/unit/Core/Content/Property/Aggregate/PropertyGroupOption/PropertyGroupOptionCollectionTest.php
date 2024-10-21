<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Property\Aggregate\PropertyGroupOption;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
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
}
