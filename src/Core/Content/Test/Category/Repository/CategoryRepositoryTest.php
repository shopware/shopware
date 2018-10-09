<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Repository;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Event\EntityDeletedEvent;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchTermInterpreter;
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
     * @var RepositoryInterface
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
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids) AND tenant_id = :tenant',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()], 'tenant' => Uuid::fromHexToBytes(Defaults::TENANT_ID)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        static::assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids) AND tenant_id = :tenant',
            ['ids' => [$childId->getBytes()], 'tenant' => Uuid::fromHexToBytes(Defaults::TENANT_ID)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);

        static::assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $parentId->getHex()]],
            Context::createDefaultContext(Defaults::TENANT_ID)
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
            'SELECT * FROM category WHERE id IN (:ids) AND tenant_id = :tenant',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()], 'tenant' => Uuid::fromHexToBytes(Defaults::TENANT_ID)],
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
        ], Context::createDefaultContext(Defaults::TENANT_ID));

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
            Context::createDefaultContext(Defaults::TENANT_ID)
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
        ], Context::createDefaultContext(Defaults::TENANT_ID));

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
        ], Context::createDefaultContext(Defaults::TENANT_ID));

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

        $this->repository->create($categories, Context::createDefaultContext(Defaults::TENANT_ID));

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', $parent));

        $builder = $this->getContainer()->get(EntityScoreQueryBuilder::class);

        $pattern = $this->getContainer()->get(SearchTermInterpreter::class)->interpret('match');
        $queries = $builder->buildScoreQueries($pattern, CategoryDefinition::class, 'category');
        $criteria->addQuery(...$queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

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
}
