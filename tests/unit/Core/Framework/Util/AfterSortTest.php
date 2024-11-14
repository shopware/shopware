<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Util\AfterSort;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AfterSort::class)]
class AfterSortTest extends TestCase
{
    public function testSortingAfterIdWithoutData(): void
    {
        $collection = new AfterSortCollection();
        $collection->sortByAfter();

        static::assertCount(0, $collection->getElements());
    }

    public function testSortingWithSingleElement(): void
    {
        $entity1 = new TestEntity();
        $entity1->setId(Uuid::randomHex());
        $entity1->setName('Root #1');

        $afterSortCollection = new AfterSortCollection([$entity1]);
        $afterSortCollection->sortByAfter();
        $first = $afterSortCollection->first();
        static::assertInstanceOf(TestEntity::class, $first);
        static::assertSame($entity1->getId(), $first->getId());
    }

    public function testSortingByAfterId(): void
    {
        $entity1 = new TestEntity();
        $entity1->setId(Uuid::randomHex());
        $entity1->setName('Root #1');

        $entity2 = new TestEntity();
        $entity2->setId(Uuid::randomHex());
        $entity2->setAfterId($entity1->getId());
        $entity2->setName('Root #2');

        $entity3 = new TestEntity();
        $entity3->setId(Uuid::randomHex());
        $entity3->setAfterId($entity2->getId());
        $entity3->setName('Root #3 (invalid)');

        $entity4 = new TestEntity();
        $entity4->setId(Uuid::randomHex());
        $entity4->setAfterId($entity3->getId());
        $entity4->setName('Root #4');

        $afterSortCollection = new AfterSortCollection([$entity1, $entity2, $entity3, $entity4]);
        $afterSortCollection->sortByAfter();

        $expectedNames = [
            $entity1->getName(),
            $entity2->getName(),
            $entity3->getName(),
            $entity4->getName(),
        ];

        $actualNames = array_map(fn (TestEntity $entity) => $entity->getName(), $afterSortCollection->getElements());

        static::assertEquals($expectedNames, \array_values($actualNames));
    }

    public function testSortingInconsistentDataWithHole(): void
    {
        $entity1 = new TestEntity();
        $entity1->setId(Uuid::randomHex());
        $entity1->setName('Root #1');

        $entity2 = new TestEntity();
        $entity2->setId(Uuid::randomHex());
        $entity2->setAfterId($entity1->getId());
        $entity2->setName('Root #2');

        $entity3 = new TestEntity();
        $entity3->setId(Uuid::randomHex());
        $entity3->setAfterId(Uuid::randomHex());
        $entity3->setName('Root #3 (invalid)');

        $entity4 = new TestEntity();
        $entity4->setId(Uuid::randomHex());
        $entity4->setAfterId($entity3->getId());
        $entity4->setName('Root #4');

        $entities = new AfterSortCollection([$entity1, $entity2, $entity3, $entity4]);
        $entities->sortByAfter();

        $expectedNames = [
            $entity1->getName(),
            $entity2->getName(),
            $entity3->getName(),
            $entity4->getName(),
        ];

        $actualNames = array_map(fn (TestEntity $entity) => $entity->getName(), $entities->getElements());

        static::assertEquals($expectedNames, \array_values($actualNames));
    }

    public function testSortingInconsistentData(): void
    {
        $entity1 = new TestEntity();
        $entity1->setId(Uuid::randomHex());
        $entity1->setName('Root #1');

        $entity2 = new TestEntity();
        $entity2->setId(Uuid::randomHex());
        $entity2->setAfterId($entity1->getId());
        $entity2->setName('Root #2');

        $entity3 = new TestEntity();
        $entity3->setId(Uuid::randomHex());
        $entity3->setAfterId(Uuid::randomHex());
        $entity3->setName('Root #3 (invalid)');

        $entity4 = new TestEntity();
        $entity4->setId(Uuid::randomHex());
        $entity4->setAfterId($entity2->getId());
        $entity4->setName('Root #4');

        $entities = new AfterSortCollection([$entity1, $entity4, $entity2, $entity3]);
        $entities->sortByAfter();

        $expectedNames = [
            $entity1->getName(),
            $entity2->getName(),
            $entity4->getName(),
            $entity3->getName(),
        ];

        $actualNames = array_map(fn (TestEntity $entity) => $entity->getName(), $entities->getElements());

        static::assertEquals($expectedNames, \array_values($actualNames));
    }

    public function testSortingByAfterIdWithMultipleNullValues(): void
    {
        $root1 = new TestEntity();
        $root1->setId(Uuid::randomHex());
        $root1->setName('Root #1');

        $root2 = new TestEntity();
        $root2->setId(Uuid::randomHex());
        $root2->setName('Root #2');
        $root2->setAfterId($root1->getId());

        $root3 = new TestEntity();
        $root3->setId(Uuid::randomHex());
        $root3->setName('Root #3');
        $root3->setAfterId($root2->getId());

        $root4 = new TestEntity();
        $root4->setId(Uuid::randomHex());
        $root4->setName('Root #4');
        $root4->setAfterId($root3->getId());

        $root5 = new TestEntity();
        $root5->setId(Uuid::randomHex());
        $root5->setName('Root #5');

        $afterSortCollection = new AfterSortCollection([$root1, $root2, $root3, $root4, $root5]);

        $afterSortCollection->sortByAfter();

        $expectedNames = $afterSortCollection->map(fn (TestEntity $entity) => $entity->getName());

        $actualNames = array_map(fn (TestEntity $entity) => $entity->getName(), $afterSortCollection->getElements());

        static::assertEquals($expectedNames, $actualNames);
    }
}

/**
 * @internal
 *
 * @extends EntityCollection<TestEntity>
 */
class AfterSortCollection extends EntityCollection
{
    public function sortByAfter(): self
    {
        $this->elements = AfterSort::sort($this->elements);

        return $this;
    }

    protected function getExpectedClass(): string
    {
        return TestEntity::class;
    }
}

/**
 * @internal
 */
class TestEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $afterId = null;

    protected string $name;

    public function getAfterId(): ?string
    {
        return $this->afterId;
    }

    public function setAfterId(string $afterId): void
    {
        $this->afterId = $afterId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
