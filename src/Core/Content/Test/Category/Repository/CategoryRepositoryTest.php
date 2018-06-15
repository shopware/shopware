<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryRepository;
use Shopware\Core\Content\Category\Event\CategoryDeletedEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\ORM\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\ORM\Write\GenericWrittenEvent;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CategoryRepositoryTest extends KernelTestCase
{
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
        self::bootKernel();
        $this->repository = self::$container->get(CategoryRepository::class);
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testDeleteParentCategoryDeletesSubCategories()
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

        $this->assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids) AND tenant_id = :tenant',
            ['ids' => [$childId->getBytes()], 'tenant' => Uuid::fromHexToBytes(Defaults::TENANT_ID)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);

        $this->assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $parentId->getHex()]],
            Context::createDefaultContext(Defaults::TENANT_ID)
        );

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);

        $event = $result->getEventByDefinition(CategoryDefinition::class);

        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);

        $this->assertEquals(
            [$parentId->getHex(), $childId->getHex()],
            $event->getIds()
        );

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids) AND tenant_id = :tenant',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()], 'tenant' => Uuid::fromHexToBytes(Defaults::TENANT_ID)],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $this->assertEmpty($exists);
    }

    public function testDeleteChildCategory()
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
        $this->assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);
        $this->assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete(
            [['id' => $childId->getHex()]],
            Context::createDefaultContext(Defaults::TENANT_ID)
        );

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);
        $event = $result->getEventByDefinition(CategoryDefinition::class);

        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);
        $this->assertEquals([$childId->getHex()], $event->getIds());

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertEmpty($exists);

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $this->assertNotEmpty($exists);
    }

    public function testWriterConsidersDeleteParent()
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

        $this->assertCount(2, $exists);

        $child = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$childId->getBytes()]],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
        $child = array_shift($child);

        $this->assertEquals($parentId->getBytes(), $child['parent_id']);

        $result = $this->repository->delete([
            ['id' => $parentId->getHex()],
        ], Context::createDefaultContext(Defaults::TENANT_ID));

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);

        $event = $result->getEventByDefinition(CategoryDefinition::class);
        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);

        $this->assertContains($parentId->getHex(), $event->getIds());
        $this->assertContains($childId->getHex(), $event->getIds(), 'Category children id did not detected by delete');
    }

    public function testSearchRanking()
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

        $builder = self::$container->get(EntityScoreQueryBuilder::class);

        $pattern = self::$container->get(SearchTermInterpreter::class)->interpret('match', Context::createDefaultContext(Defaults::TENANT_ID));
        $queries = $builder->buildScoreQueries($pattern, CategoryDefinition::class, 'category');
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, Context::createDefaultContext(Defaults::TENANT_ID));

        $this->assertCount(2, $result->getIds());

        $this->assertEquals(
            [$recordA, $recordB],
            $result->getIds()
        );

        $this->assertTrue(
            $result->getDataFieldOfId($recordA, '_score')
            >
            $result->getDataFieldOfId($recordB, '_score')
        );
    }
}
