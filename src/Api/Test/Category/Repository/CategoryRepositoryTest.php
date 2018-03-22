<?php declare(strict_types=1);

namespace Shopware\Api\Test\Category\Repository;

use Doctrine\DBAL\Connection;
use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Category\Event\Category\CategoryDeletedEvent;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Entity\RepositoryInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Term\EntityScoreQueryBuilder;
use Shopware\Api\Entity\Search\Term\SearchTermInterpreter;
use Shopware\Api\Entity\Write\EntityWriter;
use Shopware\Api\Entity\Write\GenericWrittenEvent;
use Shopware\Api\Entity\Write\Validation\RestrictDeleteViolation;
use Shopware\Api\Entity\Write\Validation\RestrictDeleteViolationException;
use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\Api\Shop\Definition\ShopTemplateDefinition;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Test\TestWriteContext;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CategoryRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function setUp()
    {
        self::bootKernel();
        $this->container = self::$kernel->getContainer();
        $this->repository = $this->container->get(CategoryRepository::class);
        $this->connection = $this->container->get(Connection::class);
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
        ], ShopContext::createDefaultContext());

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
            [['id' => $parentId->getHex()]],
            ShopContext::createDefaultContext()
        );

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);

        $event = $result->getEventByDefinition(CategoryDefinition::class);

        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);

        $this->assertEquals(
            [$parentId->getHex(), $childId->getHex()],
            $event->getIds()
        );

        $exists = $this->connection->fetchAll(
            'SELECT * FROM category WHERE id IN (:ids)',
            ['ids' => [$parentId->getBytes(), $childId->getBytes()]],
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
        ], ShopContext::createDefaultContext());

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
            ShopContext::createDefaultContext()
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
        ], ShopContext::createDefaultContext());

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
        ], ShopContext::createDefaultContext());

        $this->assertInstanceOf(GenericWrittenEvent::class, $result);

        $event = $result->getEventByDefinition(CategoryDefinition::class);
        $this->assertInstanceOf(CategoryDeletedEvent::class, $event);

        $this->assertContains($parentId->getHex(), $event->getIds());
        $this->assertContains($childId->getHex(), $event->getIds(), 'Category children id did not detected by delete');
    }

    public function testICanNotDeleteShopCategory()
    {
        $categoryId = Uuid::uuid4();
        $shopId = Uuid::uuid4();

        $this->repository->create(
            [['id' => $categoryId->getHex(), 'name' => 'System']],
            ShopContext::createDefaultContext()
        );

        $this->container->get(EntityWriter::class)->insert(
            ShopTemplateDefinition::class,
            [['id' => $shopId->getHex(), 'catalogId' => Defaults::CATALOG, 'template' => 'Test', 'name' => 'test']],
            TestWriteContext::create()
        );

        $shopRepo = $this->container->get(ShopRepository::class);
        $shopRepo->create([
            [
                'id' => $shopId->getHex(),
                'catalogIds' => [Defaults::CATALOG],
                'categoryId' => $categoryId->getHex(),
                'templateId' => $shopId->getHex(),
                'documentTemplateId' => $shopId->getHex(),
                'localeId' => '7b52d9dd-2b06-40ec-90be-9f57edf29be7',
                'currencyId' => '4c8eba11-bd35-46d7-86af-bed481a6e665',
                'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'paymentMethodId' => 'e84976ac-e9ab-4928-a3dc-c387b66dbaa6',
                'shippingMethodId' => '417beeb2-dddf-45d1-b901-88fd211343c3',
                'countryId' => 'bd5e2dcf-547e-4df6-bb1f-f58a554bc69e',
                'name' => 'test',
                'host' => 'test',
                'basePath' => 'a',
                'baseUrl' => 'a',
                'position' => 1,
            ],
        ], ShopContext::createDefaultContext());

        try {
            $this->repository->delete(
                [['id' => $categoryId->getHex()]],
                ShopContext::createDefaultContext()
            );
        } catch (RestrictDeleteViolationException $e) {
            $restrictions = $e->getRestrictions();
            /** @var RestrictDeleteViolation $restriction */
            $restriction = array_shift($restrictions);

            $this->assertEquals($categoryId->getHex(), $restriction->getId());

            $this->assertArrayHasKey(ShopDefinition::class, $restriction->getRestrictions());
            $this->assertEquals(
                [$shopId->getHex()],
                $restriction->getRestrictions()[ShopDefinition::class]
            );
        }
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

        $this->repository->create($categories, ShopContext::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('category.parentId', $parent));

        $builder = $this->container->get(EntityScoreQueryBuilder::class);

        $pattern = $this->container->get(SearchTermInterpreter::class)->interpret('match', ShopContext::createDefaultContext());
        $queries = $builder->buildScoreQueries($pattern, CategoryDefinition::class, 'category');
        $criteria->addQueries($queries);

        $result = $this->repository->searchIds($criteria, ShopContext::createDefaultContext());

        $this->assertCount(2, $result->getIds());

        $this->assertEquals(
            [$recordA, $recordB],
            $result->getIds()
        );

        $this->assertTrue(
            $result->getDataFieldOfId($recordA, 'score')
            >
            $result->getDataFieldOfId($recordB, 'score')
        );
    }
}
