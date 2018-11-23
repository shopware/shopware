<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Catalog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class CatalogTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $catalogRepository;

    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    public function setUp()
    {
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->catalogRepository = $this->getContainer()->get('catalog.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testCreateWithoutCatalogProvided(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext();
        $category = ['id' => $id->getHex(), 'name' => 'catalog test category'];

        $this->categoryRepository->create([$category], $context);

        $catalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        static::assertEquals(Defaults::CATALOG, Uuid::fromBytesToHex($catalogId));
    }

    public function testCreateWithCatalogProvidedButNotInContext(): void
    {
        $this->expectException(WriteStackException::class);

        $id = Uuid::uuid4();
        $context = Context::createDefaultContext();
        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->getHex(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $this->categoryRepository->create([$category], $context);

        $createdCatalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        static::assertEquals($catalogId, Uuid::fromBytesToHex($createdCatalogId));
    }

    public function testWithCatalogProvided(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext();
        $catalogId = $this->createCatalog($context);
        $category = [
            'id' => $id->getHex(),
            'catalogId' => $catalogId,
            'name' => 'catalog test category',
        ];

        $catalogContext = $this->addCatalogIdToContext($context, $catalogId);

        $this->categoryRepository->create([$category], $catalogContext);

        $createdCatalogId = $this->connection->fetchColumn('SELECT catalog_id FROM category WHERE id = :id', ['id' => $id->getBytes()]);

        static::assertEquals($catalogId, Uuid::fromBytesToHex($createdCatalogId));
    }

    public function testReadWithEmptyCatalogContext(): void
    {
        $this->expectException(WriteStackException::class);

        $id = Uuid::uuid4();
        $context = Context::createDefaultContext();

        $readContext = new Context(
            $context->getSourceContext(),
            [],
            [],
            $context->getCurrencyId(),
            $context->getLanguageIdChain()
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
        static::assertEquals($id->getHex(), Uuid::fromBytesToHex($createdCategory['id']));
        static::assertEquals($catalogId, Uuid::fromBytesToHex($createdCategory['catalog_id']));

        $categories = $this->categoryRepository->read(new ReadCriteria([$id->getHex()]), $readContext);
        static::assertEquals(0, $categories->count(), 'Category could be fetched but should not.');
    }

    public function testReadWithDefaultCatalogContext(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext();
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
        static::assertEquals($id->getHex(), Uuid::fromBytesToHex($createdCategory['id']));
        static::assertEquals($catalogId, Uuid::fromBytesToHex($createdCategory['catalog_id']));

        $categories = $this->categoryRepository->read(new ReadCriteria([$id->getHex()]), $context);
        static::assertEquals(0, $categories->count(), 'Category could be fetched but should not.');
    }

    public function testReadWithCorrectCatalogContext(): void
    {
        $id = Uuid::uuid4();
        $context = Context::createDefaultContext();
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
        static::assertEquals($id->getHex(), Uuid::fromBytesToHex($createdCategory['id']));
        static::assertEquals($catalogId, Uuid::fromBytesToHex($createdCategory['catalog_id']));

        $categories = $this->categoryRepository->read(new ReadCriteria([$id->getHex()]), $context);
        static::assertEquals(1, $categories->count(), 'Category was not fetched but should be.');
    }

    public function testReadWithMultipleCatalogs(): void
    {
        $context = Context::createDefaultContext();

        $id1 = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $id3 = Uuid::uuid4();
        $catalogId1 = $this->createCatalog($context);
        $catalogId2 = $this->createCatalog($context);

        $fullContext = Context::createDefaultContext();
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
            $context->getSourceContext(),
            [$catalogId1, $catalogId2],
            $context->getRules(),
            $context->getCurrencyId(),
            $context->getLanguageIdChain()
        );

        $foundCategories = $this->categoryRepository->read(new ReadCriteria(array_column($categories, 'id')), $context);
        static::assertEquals(2, $foundCategories->count());

        // read with default and another two enabled catalogs
        $context = new Context(
            $context->getSourceContext(),
            [$catalogId1, $catalogId2, Defaults::CATALOG],
            [],
            $context->getCurrencyId(),
            [$context->getLanguageId()]
        );

        $foundCategories = $this->categoryRepository->read(new ReadCriteria(array_column($categories, 'id')), $context);
        static::assertEquals(3, $foundCategories->count());
    }

    public function testToOneRead(): void
    {
        $id = Uuid::uuid4();
        $id2 = Uuid::uuid4();
        $parentId = Uuid::uuid4();
        $context = Context::createDefaultContext();
        $catalogId = $this->createCatalog($context);

        $context = new Context(
            $context->getSourceContext(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageIdChain()
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
        static::assertContains($ids[0], $foundIds);
        static::assertContains($ids[1], $foundIds);
        static::assertContains($ids[2], $foundIds);
        static::assertEquals(Uuid::fromStringToBytes($catalogId), array_unique(array_column($createdCategory, 'catalog_id'))[0]);

        $criteria = new ReadCriteria([$parentId->getHex()]);
        $criteria->addAssociation('children');
        $categories = $this->categoryRepository->read($criteria, $context);

        static::assertEquals(1, $categories->count(), 'Category was not fetched but should be.');
        static::assertEquals(2, $categories->first()->getChildren()->count());

        static::assertEquals(
            [
                $id->getHex() => $id->getHex(),
                $id2->getHex() => $id2->getHex(),
            ],
            $categories->first()->getChildren()->map(function (CategoryEntity $category) {
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
        $context = Context::createDefaultContext();
        $catalogId = $this->createCatalog($context);

        $context = new Context(
            $context->getSourceContext(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageIdChain()
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
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'taxRate' => 10],
                ],
                [
                    'id' => $productId2->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 2',
                    'price' => ['gross' => 20, 'net' => 20],
                    'manufacturer' => ['id' => $manufacturerId->getHex(), 'name' => 'catalog manufacturer', 'catalogId' => $catalogId],
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'taxRate' => 10],
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $categoryId->getBytes()]);
        static::assertEquals($categoryId->getBytes(), $createdCategory['id']);
        static::assertEquals(Uuid::fromStringToBytes($catalogId), $createdCategory['catalog_id']);

        // verify product mapping has been created correctly
        $products = $this->connection->fetchAll('SELECT product_id, category_id FROM product_category WHERE category_id = :id', ['id' => $categoryId->getBytes()]);
        static::assertContains($categoryId->getBytes(), array_column($products, 'category_id'));
        static::assertContains($productId1->getBytes(), array_column($products, 'product_id'));
        static::assertContains($productId2->getBytes(), array_column($products, 'product_id'));

        // should work with context used to create the entities
        $products = $this->productRepository->read(new ReadCriteria([$productId1->getHex(), $productId2->getHex()]), $context);
        static::assertEquals(2, $products->count(), 'Products were not fetched correctly');

        // should not work as catalog differs from the default
        $context = Context::createDefaultContext();
        $products = $this->productRepository->read(new ReadCriteria([$productId1->getHex(), $productId2->getHex()]), $context);
        static::assertEquals(0, $products->count(), 'Products should not be fetched');
    }

    public function testSearch(): void
    {
        $productId1 = Uuid::uuid4();
        $productId2 = Uuid::uuid4();
        $categoryId = Uuid::uuid4();
        $manufacturerId = Uuid::uuid4();
        $taxId = Uuid::uuid4();
        $context = Context::createDefaultContext();
        $catalogId = $this->createCatalog($context);

        $context = new Context(
            $context->getSourceContext(),
            [$catalogId],
            [],
            $context->getCurrencyId(),
            $context->getLanguageIdChain()
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
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'taxRate' => 10],
                ],
                [
                    'id' => $productId2->getHex(),
                    'catalogId' => $catalogId,
                    'name' => 'product catalog 2',
                    'price' => ['gross' => 10, 'net' => 10],
                    'manufacturer' => ['id' => $manufacturerId->getHex(), 'name' => 'catalog manufacturer', 'catalogId' => $catalogId],
                    'tax' => ['id' => $taxId->getHex(), 'name' => '10%', 'taxRate' => 10],
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $context);

        // verify category has been created correctly
        $createdCategory = $this->connection->fetchAssoc('SELECT id, catalog_id FROM category WHERE id = :id', ['id' => $categoryId->getBytes()]);
        static::assertEquals($categoryId->getBytes(), $createdCategory['id']);
        static::assertEquals(Uuid::fromStringToBytes($catalogId), $createdCategory['catalog_id']);

        // verify product mapping has been created correctly
        $products = $this->connection->fetchAll('SELECT product_id, category_id FROM product_category WHERE category_id = :id', ['id' => $categoryId->getBytes()]);
        static::assertContains($categoryId->getBytes(), array_column($products, 'category_id'));
        static::assertContains($productId1->getBytes(), array_column($products, 'product_id'));
        static::assertContains($productId2->getBytes(), array_column($products, 'product_id'));

        // should work with context used to create the entities
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $categoryId->getHex()));

        $products = $this->productRepository->search($criteria, $context);
        static::assertEquals(2, $products->count(), 'Products were not fetched correctly');

        // should not work as catalog differs from the default
        $context = Context::createDefaultContext();
        $products = $this->productRepository->search($criteria, $context);
        static::assertEquals(0, $products->count(), 'Products should not be fetched');
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
            $context->getSourceContext(),
            array_merge($context->getCatalogIds(), [$catalogId]),
            $context->getRules(),
            $context->getCurrencyId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $context->getCurrencyFactor()
        );
    }
}
