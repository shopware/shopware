<?php declare(strict_types=1);

namespace Shopware\Api\Test\Catalog;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Catalog\Repository\CatalogRepository;
use Shopware\Api\Category\Repository\CategoryRepository;
use Shopware\Api\Category\Struct\CategoryBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CatalogTest extends KernelTestCase
{
    /**
     * @var CategoryRepository
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
     * @var ProductRepository
     */
    private $productRepository;

    public function setUp()
    {
        $kernel = static::bootKernel();

        $this->categoryRepository = $kernel->getContainer()->get(CategoryRepository::class);
        $this->catalogRepository = $kernel->getContainer()->get(CatalogRepository::class);
        $this->productRepository = $kernel->getContainer()->get(ProductRepository::class);
        $this->connection = $kernel->getContainer()->get(Connection::class);
//        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
//        $this->connection->rollBack();
    }

    public function testCreateWithoutCatalogProvided(): void
    {
        $id = Uuid::uuid4();
        $context = ShopContext::createDefaultContext();
        $category = ['id' => $id->toString(), 'name' => 'catalog test category'];

        $this->categoryRepository->create([$category], $context);

        $catalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        $this->assertEquals(Defaults::CATALOG, Uuid::fromBytes($catalogId)->toString());
    }

    public function testWithCatalogProvided(): void
    {
        $id = Uuid::uuid4();
        $context = ShopContext::createDefaultContext();
        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->toString(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        $createdCatalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        $this->assertEquals($catalogId, Uuid::fromBytes($createdCatalogId)->toString());
    }

    public function testReadWithEmptyCatalogContext(): void
    {
        $id = Uuid::uuid4();
        $context = ShopContext::createDefaultContext();

        $context = new ShopContext(
            $context->getApplicationId(),
            [],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->toString(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEquals($id->toString(), Uuid::fromBytes($createdCategory['id'])->toString());
        $this->assertEquals($catalogId, Uuid::fromBytes($createdCategory['catalog_id'])->toString());

        $categories = $this->categoryRepository->readBasic([$id->toString()], $context);
        $this->assertEquals(0, $categories->count(), 'Category could be fetched but should not.');
    }

    public function testReadWithDefaultCatalogContext(): void
    {
        $id = Uuid::uuid4();
        $context = ShopContext::createDefaultContext();
        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->toString(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEquals($id->toString(), Uuid::fromBytes($createdCategory['id'])->toString());
        $this->assertEquals($catalogId, Uuid::fromBytes($createdCategory['catalog_id'])->toString());

        $categories = $this->categoryRepository->readBasic([$id->toString()], $context);
        $this->assertEquals(0, $categories->count(), 'Category could be fetched but should not.');
    }

    public function testReadWithCorrectCatalogContext(): void
    {
        $id = Uuid::uuid4();
        $context = ShopContext::createDefaultContext();
        $catalogId = $this->createCatalog($context);

        $context = new ShopContext(
            $context->getApplicationId(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $category = [
            'id' => $id->toString(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);
        $this->assertEquals($id->toString(), Uuid::fromBytes($createdCategory['id'])->toString());
        $this->assertEquals($catalogId, Uuid::fromBytes($createdCategory['catalog_id'])->toString());

        $categories = $this->categoryRepository->readBasic([$id->toString()], $context);
        $this->assertEquals(1, $categories->count(), 'Category was not fetched but should be.');
    }

    public function testReadWithMultipleCatalogs(): void
    {
        $context = ShopContext::createDefaultContext();

        $id1 = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $id3 = Uuid::uuid4();
        $catalogId1 = $this->createCatalog($context);
        $catalogId2 = $this->createCatalog($context);

        $categories = [
            ['id' => $id1->toString(), 'catalogId' => $catalogId1, 'name' => 'test category catalog1'],
            ['id' => $id2->toString(), 'catalogId' => $catalogId2, 'name' => 'test category catalog2'],
            ['id' => $id3->toString(), 'name' => 'test category default catalog'],
        ];

        $this->categoryRepository->create($categories, $context);

        // read with two enabled catalogs
        $context = new ShopContext(
            $context->getApplicationId(),
            [$catalogId1, $catalogId2],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $foundCategories = $this->categoryRepository->readBasic(array_column($categories, 'id'), $context);
        $this->assertEquals(2, $foundCategories->count());

        // read with default and another two enabled catalogs
        $context = new ShopContext(
            $context->getApplicationId(),
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
        $context = ShopContext::createDefaultContext();
        $catalogId = $this->createCatalog($context);

        $context = new ShopContext(
            $context->getApplicationId(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $category = [
            'id' => $parentId->toString(),
            'catalogId' => $catalogId,
            'name' => 'parent category',
            'children' => [
                [
                    'id' => $id->toString(),
                    'catalogId' => $catalogId,
                    'name' => 'catalog test category',
                    'parentId' => $parentId->toString(),
                ],
                [
                    'id' => $id2->toString(),
                    'catalogId' => $catalogId,
                    'name' => 'catalog second category',
                    'parentId' => $parentId->toString(),
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
        $this->assertEquals(Uuid::fromString($catalogId)->getBytes(), array_unique(array_column($createdCategory, 'catalog_id'))[0]);

        $categories = $this->categoryRepository->readDetail([$parentId->toString()], $context);

        $this->assertEquals(1, $categories->count(), 'Category was not fetched but should be.');
        $this->assertEquals(2, $categories->first()->getChildren()->count());

        $this->assertEquals(
            [
                $id->toString() => $id->toString(),
                $id2->toString() => $id2->toString(),
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
        $context = ShopContext::createDefaultContext();
        $catalogId = $this->createCatalog($context);

        $context = new ShopContext(
            $context->getApplicationId(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $category = [
            'id' => $categoryId->toString(),
            'catalogId' => $catalogId,
            'name' => 'manytomany category',
            'products' => [
                [
                    'id' => $productId1->toString(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 1',
                    'price' => 10,
                    'manufacturer' => ['id' => $manufacturerId->toString(), 'name' => 'catalog manufacturer'],
                    'tax' => ['id' => $taxId->toString(), 'name' => '10%', 'rate' => 10],
                ],
                [
                    'id' => $productId2->toString(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 2',
                    'price' => 20,
                    'manufacturer' => ['id' => $manufacturerId->toString(), 'name' => 'catalog manufacturer'],
                    'tax' => ['id' => $taxId->toString(), 'name' => '10%', 'rate' => 10],
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertEquals($categoryId->getBytes(), $createdCategory['id']);
        $this->assertEquals(Uuid::fromString($catalogId)->getBytes(), $createdCategory['catalog_id']);

        // verify product mapping has been created correctly
        $products = $this->connection->fetchAll('SELECT product_id, category_id FROM product_category WHERE category_id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertContains($categoryId->getBytes(), array_column($products, 'category_id'));
        $this->assertContains($productId1->getBytes(), array_column($products, 'product_id'));
        $this->assertContains($productId2->getBytes(), array_column($products, 'product_id'));

        // should work with context used to create the entities
        $products = $this->productRepository->readBasic([$productId1->toString(), $productId2->toString()], $context);
        $this->assertEquals(2, $products->count(), 'Products were not fetched correctly');

        // should not work as catalog differs from the default
        $context = ShopContext::createDefaultContext();
        $products = $this->productRepository->readBasic([$productId1->toString(), $productId2->toString()], $context);
        $this->assertEquals(0, $products->count(), 'Products should not be fetched');
    }

    public function testSearch()
    {
        $productId1 = Uuid::uuid4();
        $productId2 = Uuid::uuid4();
        $categoryId = Uuid::uuid4();
        $manufacturerId = Uuid::uuid4();
        $taxId = Uuid::uuid4();
        $context = ShopContext::createDefaultContext();
        $catalogId = $this->createCatalog($context);

        $context = new ShopContext(
            $context->getApplicationId(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageId()
        );

        $category = [
            'id' => $categoryId->toString(),
            'catalogId' => $catalogId,
            'name' => 'manytomany category',
            'products' => [
                [
                    'id' => $productId1->toString(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 1',
                    'price' => 10,
                    'manufacturer' => ['id' => $manufacturerId->toString(), 'name' => 'catalog manufacturer'],
                    'tax' => ['id' => $taxId->toString(), 'name' => '10%', 'rate' => 10],
                ],
                [
                    'id' => $productId2->toString(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 2',
                    'price' => 20,
                    'manufacturer' => ['id' => $manufacturerId->toString(), 'name' => 'catalog manufacturer'],
                    'tax' => ['id' => $taxId->toString(), 'name' => '10%', 'rate' => 10],
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertEquals($categoryId->getBytes(), $createdCategory['id']);
        $this->assertEquals(Uuid::fromString($catalogId)->getBytes(), $createdCategory['catalog_id']);

        // verify product mapping has been created correctly
        $products = $this->connection->fetchAll('SELECT product_id, category_id FROM product_category WHERE category_id = :id', ['id' => $categoryId->getBytes()]);
        $this->assertContains($categoryId->getBytes(), array_column($products, 'category_id'));
        $this->assertContains($productId1->getBytes(), array_column($products, 'product_id'));
        $this->assertContains($productId2->getBytes(), array_column($products, 'product_id'));

        // should work with context used to create the entities
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.categories.id', $categoryId->toString()));

        $products = $this->productRepository->search($criteria, $context);
        $this->assertEquals(2, $products->count(), 'Products were not fetched correctly');

        // should not work as catalog differs from the default
        $context = ShopContext::createDefaultContext();
        $products = $this->productRepository->search($criteria, $context);
        $this->assertEquals(0, $products->count(), 'Products should not be fetched');
    }

    private function createCatalog(ShopContext $context): string
    {
        $catalogId = Uuid::uuid4();
        $catalog = ['id' => $catalogId->toString(), 'name' => 'unit test catalog'];
        $this->catalogRepository->create([$catalog], $context);

        return $catalogId->toString();
    }
}
