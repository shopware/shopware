<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Catalog;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Catalog\CatalogRepository;
use Shopware\Core\Content\Category\CategoryRepository;
use Shopware\Core\Content\Category\Struct\CategoryBasicStruct;
use Shopware\Core\Content\Product\ProductRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\Framework\ORM\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CatalogTest extends KernelTestCase
{
    /**
     * @var \Shopware\Core\Content\Category\CategoryRepository
     */
    private $categoryRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CatalogRepository
     */
    private $catalogRepository;

    /**
     * @var \Shopware\Core\Content\Product\ProductRepository
     */
    private $productRepository;

    public function setUp()
    {
        static::bootKernel();

        $this->categoryRepository = self::$container->get(CategoryRepository::class);
        $this->catalogRepository = self::$container->get(CatalogRepository::class);
        $this->productRepository = self::$container->get(ProductRepository::class);
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testCreateWithoutCatalogProvided(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $category = ['id' => $id->getHex(), 'name' => 'catalog test category'];

        $this->categoryRepository->create([$category], $context);

        $catalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        $this->assertEquals(Defaults::CATALOG, Uuid::fromBytesToHex($catalogId));
    }

    public function testCreateWithCatalogProvidedButNotInContext(): void
    {
        $this->expectException(WriteStackException::class);

        $id = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->getHex(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        $createdCatalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        $this->assertEquals($catalogId, Uuid::fromBytesToHex($createdCatalogId));
    }

    public function testWithCatalogProvided(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->getHex(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $catalogContext = $this->addCatalogIdToContext($context, $catalogId);

        $this->categoryRepository->create([$category], $catalogContext);

        $createdCatalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        $this->assertEquals($catalogId, Uuid::fromBytesToHex($createdCatalogId));
    }

    public function testReadWithEmptyCatalogContext(): void
    {
        $this->expectException(WriteStackException::class);

        $id = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $readContext = new Context(
            Defaults::TENANT_ID,
            $context->getTouchpointId(),
            [],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->getHex(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEquals($id->getHex(), Uuid::fromBytesToHex($createdCategory['id']));
        $this->assertEquals($catalogId, Uuid::fromBytesToHex($createdCategory['catalog_id']));

        $categories = $this->categoryRepository->readBasic([$id->getHex()], $readContext);
        $this->assertEquals(0, $categories->count(), 'Category could be fetched but should not.');
    }

    public function testReadWithDefaultCatalogContext(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $catalogId = $this->createCatalog($context);
        $catalogContext = $this->addCatalogIdToContext($context, $catalogId);
        $category = [
            'id' => $id->getHex(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $catalogContext);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEquals($id->getHex(), Uuid::fromBytesToHex($createdCategory['id']));
        $this->assertEquals($catalogId, Uuid::fromBytesToHex($createdCategory['catalog_id']));

        $categories = $this->categoryRepository->readBasic([$id->getHex()], $context);
        $this->assertEquals(0, $categories->count(), 'Category could be fetched but should not.');
    }

    public function testReadWithCorrectCatalogContext(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $catalogId = $this->createCatalog($context);

        $context = $this->addCatalogIdToContext($context, $catalogId);

        $category = [
            'id' => $id->getHex(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEquals($id->getHex(), Uuid::fromBytesToHex($createdCategory['id']));
        $this->assertEquals($catalogId, Uuid::fromBytesToHex($createdCategory['catalog_id']));

        $categories = $this->categoryRepository->readBasic([$id->getHex()], $context);
        $this->assertEquals(1, $categories->count(), 'Category was not fetched but should be.');
    }

    public function testReadWithMultipleCatalogs(): void
    {
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id1 = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $id3 = Uuid::uuid4();
        $catalogId1 = $this->createCatalog($context);
        $catalogId2 = $this->createCatalog($context);

        $fullContext = Context::createDefaultContext(Defaults::TENANT_ID);
        $fullContext = $this->addCatalogIdToContext($fullContext, $catalogId1);
        $fullContext = $this->addCatalogIdToContext($fullContext, $catalogId2);

        $categories = [
            ['id' => $id1->getHex(), 'catalogId' => $catalogId1, 'name' => 'test category catalog1'],
            ['id' => $id2->getHex(), 'catalogId' => $catalogId2, 'name' => 'test category catalog2'],
            ['id' => $id3->getHex(), 'name' => 'test category default catalog'],
        ];

        $this->categoryRepository->create($categories, $fullContext);

        // read with two enabled catalogs
        $context = new Context(
            Defaults::TENANT_ID,
            $context->getTouchpointId(),
            [$catalogId1, $catalogId2],
            $context->getRules(),
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $foundCategories = $this->categoryRepository->readBasic(array_column($categories, 'id'), $context);
        $this->assertEquals(2, $foundCategories->count());

        // read with default and another two enabled catalogs
        $context = new Context(
            Defaults::TENANT_ID,
            $context->getTouchpointId(),
            [$catalogId1, $catalogId2, Defaults::CATALOG],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $foundCategories = $this->categoryRepository->readBasic(array_column($categories, 'id'), $context);
        $this->assertEquals(3, $foundCategories->count());
    }

    public function testToOneRead(): void
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $parentId = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $catalogId = $this->createCatalog($context);

        $context = new Context(
            Defaults::TENANT_ID,
            $context->getTouchpointId(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $category = [
            'id' => $parentId->getHex(),
            'catalogId' => $catalogId,
            'name' => 'parent category',
            'children' => [
                [
                    'id' => $id->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'catalog test category',
                    'parentId' => $parentId->getHex(),
                ],
                [
                    'id' => $id2->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'catalog second category',
                    'parentId' => $parentId->getHex(),
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $context);

        $ids = [$parentId->getBytes(), $id->getBytes(), $id2->getBytes()];

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAll(
            'SELECT id, catalog_id FROM category WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $foundIds = array_column($createdCategory, 'id');
        $this->assertContains($ids[0], $foundIds);
        $this->assertContains($ids[1], $foundIds);
        $this->assertContains($ids[2], $foundIds);
        $this->assertEquals(Uuid::fromStringToBytes($catalogId), array_unique(array_column($createdCategory, 'catalog_id'))[0]);

        $categories = $this->categoryRepository->readDetail([$parentId->getHex()], $context);

        $this->assertEquals(1, $categories->count(), 'Category was not fetched but should be.');
        $this->assertEquals(2, $categories->first()->getChildren()->count());

        $this->assertEquals(
            [
                $id->getHex() => $id->getHex(),
                $id2->getHex() => $id2->getHex(),
            ],
            $categories->first()->getChildren()->map(function (CategoryBasicStruct $category) {
                return $category->getId();
            }));
    }

    public function testToManyRead(): void
    {
        $productId1 = Uuid::uuid4();
        $productId2 = Uuid::uuid4();
        $categoryId = Uuid::uuid4();
        $manufacturerId = Uuid::uuid4();
        $taxId = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $catalogId = $this->createCatalog($context);

        $context = new Context(
            Defaults::TENANT_ID,
            $context->getTouchpointId(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $category = [
            'id' => $categoryId->getHex(),
            'catalogId' => $catalogId,
            'name' => 'manytomany category',
            'products' => [
                [
                    'id' => $productId1->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 1',
                    'price' => ['gross' => 10, 'net' => 10],
                    'manufacturer' => ['id' => $manufacturerId->getHex(), 'name' => 'catalog manufacturer', 'catalogId' => $catalogId],
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'rate' => 10],
                ],
                [
                    'id' => $productId2->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 2',
                    'price' => ['gross' => 20, 'net' => 20],
                    'manufacturer' => ['id' => $manufacturerId->getHex(), 'name' => 'catalog manufacturer', 'catalogId' => $catalogId],
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'rate' => 10],
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertEquals($categoryId->getBytes(), $createdCategory['id']);
        $this->assertEquals(Uuid::fromStringToBytes($catalogId), $createdCategory['catalog_id']);

        // verify product mapping has been created correctly
        $products = $this->connection->fetchAll('SELECT product_id, category_id FROM product_category WHERE category_id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertContains($categoryId->getBytes(), array_column($products, 'category_id'));
        $this->assertContains($productId1->getBytes(), array_column($products, 'product_id'));
        $this->assertContains($productId2->getBytes(), array_column($products, 'product_id'));

        // should work with context used to create the entities
        $products = $this->productRepository->readBasic([$productId1->getHex(), $productId2->getHex()], $context);
        $this->assertEquals(2, $products->count(), 'Products were not fetched correctly');

        // should not work as catalog differs from the default
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $products = $this->productRepository->readBasic([$productId1->getHex(), $productId2->getHex()], $context);
        $this->assertEquals(0, $products->count(), 'Products should not be fetched');
    }

    public function testSearch()
    {
        $productId1 = Uuid::uuid4();
        $productId2 = Uuid::uuid4();
        $categoryId = Uuid::uuid4();
        $manufacturerId = Uuid::uuid4();
        $taxId = Uuid::uuid4();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $catalogId = $this->createCatalog($context);

        $context = new Context(
            Defaults::TENANT_ID,
            $context->getTouchpointId(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $category = [
            'id' => $categoryId->getHex(),
            'catalogId' => $catalogId,
            'name' => 'manytomany category',
            'products' => [
                [
                    'id' => $productId1->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 1',
                    'price' => ['gross' => 10, 'net' => 10],
                    'manufacturer' => ['id' => $manufacturerId->getHex(), 'name' => 'catalog manufacturer', 'catalogId' => $catalogId],
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'rate' => 10],
                ],
                [
                    'id' => $productId2->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 2',
                    'price' => ['gross' => 10, 'net' => 10],
                    'manufacturer' => ['id' => $manufacturerId->getHex(), 'name' => 'catalog manufacturer', 'catalogId' => $catalogId],
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'rate' => 10],
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertEquals($categoryId->getBytes(), $createdCategory['id']);
        $this->assertEquals(Uuid::fromStringToBytes($catalogId), $createdCategory['catalog_id']);

        // verify product mapping has been created correctly
        $products = $this->connection->fetchAll('SELECT product_id, category_id FROM product_category WHERE category_id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertContains($categoryId->getBytes(), array_column($products, 'category_id'));
        $this->assertContains($productId1->getBytes(), array_column($products, 'product_id'));
        $this->assertContains($productId2->getBytes(), array_column($products, 'product_id'));

        // should work with context used to create the entities
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $categoryId->getHex()));

        $products = $this->productRepository->search($criteria, $context);
        $this->assertEquals(2, $products->count(), 'Products were not fetched correctly');

        // should not work as catalog differs from the default
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $products = $this->productRepository->search($criteria, $context);
        $this->assertEquals(0, $products->count(), 'Products should not be fetched');
    }

    private function createCatalog(Context $context): string
    {
        $catalogId = Uuid::uuid4();
        $catalog = ['id' => $catalogId->getHex(), 'name' => 'unit test catalog'];
        $this->catalogRepository->create([$catalog], $context);

        return $catalogId->getHex();
    }

    private function addCatalogIdToContext(Context $context, string $catalogId): Context
    {
        return new Context(
            Defaults::TENANT_ID,
            $context->getTouchpointId(),
            array_merge($context->getCatalogIds(), [$catalogId]),
            $context->getRules(),
            $context->getCurrencyId(),
            $context->getLanguageId(),
            $context->getFallbackLanguageId(),
            $context->getVersionId(),
            $context->getCurrencyFactor()
        );
    }
}
