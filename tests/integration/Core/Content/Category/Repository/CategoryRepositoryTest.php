<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CategoryRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = $this->getContainer()->get('category.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testDeleteParentCategoryDeletesSubCategories(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->repository->create([
            ['id' => $parentId, 'name' => 'parent-1'],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertCount(2, $exists);

        $child = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        $child = array_shift($child);

        static::assertIsArray($child);
        static::assertSame(Uuid::fromHexToBytes($parentId), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $parentId]],
            Context::createDefaultContext()
        );

        $event = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        static::assertInstanceOf(EntityDeletedEvent::class, $event);

        static::assertSame(
            [$parentId, $childId],
            $event->getIds()
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertEmpty($exists);
    }

    public function testDeleteChildCategory(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->repository->create([
            ['id' => $parentId, 'name' => 'parent-1'],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertCount(2, $exists);

        $child = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        $child = array_shift($child);

        static::assertIsArray($child);
        static::assertSame(Uuid::fromHexToBytes($parentId), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $childId]],
            Context::createDefaultContext()
        );

        $event = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        static::assertInstanceOf(EntityDeletedEvent::class, $event);
        static::assertSame([$childId], $event->getIds());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertEmpty($exists);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        static::assertNotEmpty($exists);
    }

    public function testWriterConsidersDeleteParent(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->repository->create([
            ['id' => $parentId, 'name' => 'parent-1'],
            ['id' => $childId, 'name' => 'child', 'parentId' => $parentId],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($parentId), Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertCount(2, $exists);

        $child = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($childId)]],
            ['ids' => ArrayParameterType::BINARY]
        );
        $child = array_shift($child);

        static::assertIsArray($child);
        static::assertSame(Uuid::fromHexToBytes($parentId), $child['parent_id']);

        $result = $this->repository->delete([
            ['id' => $parentId],
        ], Context::createDefaultContext());

        $event = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityDeletedEvent::class, $event);

        static::assertContains($parentId, $event->getIds());
        static::assertContains($childId, $event->getIds(), 'Category children id did not detected by delete');
    }

    public function testCreateNesting(): void
    {
        $parent = Uuid::randomHex();
        $recordA = Uuid::randomHex();
        $recordB = Uuid::randomHex();
        $recordC = Uuid::randomHex();

        $categories = [
            ['id' => $parent, 'name' => 'First Level Category'],
            ['id' => $recordA, 'name' => 'Second Level Category', 'parentId' => $parent],
            ['id' => $recordC, 'name' => 'Third Level Category', 'parentId' => $recordA],
            ['id' => $recordB, 'name' => 'Second Level Category 2', 'parentId' => $parent, 'afterCategoryId' => $recordA],
        ];

        $this->repository->create($categories, Context::createDefaultContext());

        $criteria = new Criteria([$parent]);
        $criteria->addAssociation('children');

        $result = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();

        $first = $result->first();
        static::assertNotNull($first);

        // First Level Category should have Level 1
        static::assertSame($parent, $first->getId());
        static::assertSame(1, $first->getLevel());

        // Second Level Categories should have Level 2
        $children = $first->getChildren();
        static::assertNotNull($children);
        $children->sortByPosition();
        $childrenArray = array_values($children->getElements());
        static::assertSame($recordA, $childrenArray[0]->getId());
        static::assertSame(2, $childrenArray[0]->getLevel());
        static::assertSame($recordB, $childrenArray[1]->getId());
        static::assertSame(2, $childrenArray[1]->getLevel());

        $criteria = new Criteria([$recordA]);
        $criteria->addAssociation('children');

        $result = $this->repository->search($criteria, Context::createDefaultContext())->getEntities();
        $firstCategory = $result->first();
        static::assertNotNull($firstCategory);

        // Second Level Category should have Level 2
        static::assertSame($recordA, $firstCategory->getId());
        static::assertSame(2, $firstCategory->getLevel());

        // Third Level Category should have Level 3
        $children = $firstCategory->getChildren();
        static::assertNotNull($children);
        $firstChild = $children->first();
        static::assertNotNull($firstChild);
        static::assertSame($recordC, $firstChild->getId());
        static::assertSame(3, $firstChild->getLevel());
    }
}
