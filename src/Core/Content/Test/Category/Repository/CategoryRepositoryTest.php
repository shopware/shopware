<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class CategoryRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    public function setUp()
    {
        $this->repository = $this->getContainer()->get('category.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testDeleteParentCategoryDeletesSubCategories(): void
    {
        $parentId = Uuid::uuid4();
        $childId = Uuid::uuid4();

        $this->repository->create([
            ['id' => $parentId->getHex(), 'name' => 'parent-1'],
            ['id' => $childId->getHex(), 'name' => 'child', 'parentId' => $parentId->getHex()],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);

        static::assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $parentId->getHex()]],
            Context::createDefaultContext()
        );

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        /** @var EntityWrittenContainerEvent $result */
        $event = $result->getEventByDefinition(CategoryDefinition::class);

        static::assertInstanceOf(EntityDeletedEvent::class, $event);

        static::assertEquals(
            [$parentId->getHex(), $childId->getHex()],
            $event->getIds()
        );

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertEmpty($exists);
    }

    public function testDeleteChildCategory(): void
    {
        $parentId = Uuid::uuid4();
        $childId = Uuid::uuid4();

        $this->repository->create([
            ['id' => $parentId->getHex(), 'name' => 'parent-1'],
            ['id' => $childId->getHex(), 'name' => 'child', 'parentId' => $parentId->getHex()],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);
        static::assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $childId->getHex()]],
            Context::createDefaultContext()
        );

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);
        $event = $result->getEventByDefinition(CategoryDefinition::class);

        static::assertInstanceOf(EntityDeletedEvent::class, $event);
        static::assertEquals([$childId->getHex()], $event->getIds());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertEmpty($exists);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        static::assertNotEmpty($exists);
    }

    public function testWriterConsidersDeleteParent(): void
    {
        $parentId = Uuid::uuid4();
        $childId = Uuid::uuid4();

        $this->repository->create([
            ['id' => $parentId->getHex(), 'name' => 'parent-1'],
            ['id' => $childId->getHex(), 'name' => 'child', 'parentId' => $parentId->getHex()],
        ], Context::createDefaultContext());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);

        static::assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete([
            ['id' => $parentId->getHex()],
        ], Context::createDefaultContext());

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $event = $result->getEventByDefinition(CategoryDefinition::class);
        static::assertInstanceOf(EntityDeletedEvent::class, $event);

        static::assertContains($parentId->getHex(), $event->getIds());
        static::assertContains($childId->getHex(), $event->getIds(), 'Category children id did not detected by delete');
    }

    public function testSearchRanking(): void
    {
        $parent = Uuid::uuid4()->getHex();
        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();

        $categories = [
            ['id' => $parent, 'name' => 'test'],
            ['id' => $recordA, 'name' => 'match', 'parentId' => $parent],
            ['id' => $recordB, 'name' => 'not', 'metaKeywords' => 'match', 'parentId' => $parent],
        ];

        $this->repository->create($categories, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.parentId', $parent));

        $builder = $this->getContainer()->get(EntityScoreQueryBuilder::class);

        $pattern = $this->getContainer()->get(SearchTermInterpreter::class)->interpret('match');
        $queries = $builder->buildScoreQueries($pattern, CategoryDefinition::class, 'category');
        $criteria->addQuery(...$queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(2, $result->getIds());

        static::assertEquals(
            [$recordA, $recordB],
            $result->getIds()
        );

        static::assertTrue(
            $result->getDataFieldOfId($recordA, '_score')
            >
            $result->getDataFieldOfId($recordB, '_score')
        );
    }

    public function testCreateNesting(): void
    {
        $parent = Uuid::uuid4()->getHex();
        $recordA = Uuid::uuid4()->getHex();
        $recordB = Uuid::uuid4()->getHex();
        $recordC = Uuid::uuid4()->getHex();

        $categories = [
            ['id' => $parent, 'name' => 'First Level Category', 'position' => 0],
            ['id' => $recordA, 'name' => 'Second Level Category', 'position' => 0, 'parentId' => $parent],
            ['id' => $recordC, 'name' => 'Third Level Category', 'position' => 0, 'parentId' => $recordA],
            ['id' => $recordB, 'name' => 'Second Level Category 2', 'position' => 1, 'parentId' => $parent],
        ];

        $this->repository->create($categories, Context::createDefaultContext());

        $criteria = new Criteria([$parent]);
        $criteria->addAssociation('children');

        /** @var CategoryCollection $result */
        $result = $this->repository->read($criteria, Context::createDefaultContext());

        /** @var CategoryEntity $first */
        $first = $result->first();

        //First Level Category should have Level 1
        static::assertEquals($parent, $first->getId());
        static::assertEquals(1, $first->getLevel());

        //Second Level Categories should have Level 2
        /** @var CategoryCollection $children */
        $children = $first->getChildren();
        $children->sortByPosition();
        $childrenArray = array_values($children->getElements());
        static::assertEquals($recordA, $childrenArray[0]->getId());
        static::assertEquals(2, $childrenArray[0]->getLevel());
        static::assertEquals($recordB, $childrenArray[1]->getId());
        static::assertEquals(2, $childrenArray[1]->getLevel());

        $criteria = new Criteria([$recordA]);
        $criteria->addAssociation('children');

        /** @var CategoryCollection $result */
        $result = $this->repository->read($criteria, Context::createDefaultContext());

        //Second Level Category should have Level 2
        static::assertEquals($recordA, $result->first()->getId());
        static::assertEquals(2, $result->first()->getLevel());

        //Third Level Category should have Level 3
        $children = $result->first()->getChildren();
        static::assertEquals($recordC, $children->first()->getId());
        static::assertEquals(3, $children->first()->getLevel());
    }
}
