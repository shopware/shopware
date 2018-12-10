<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Version;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryStruct;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class VersioningTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

    public function setUp()
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->categoryRepository = $this->getContainer()->get('category.repository');
    }

    public function testICanVersionPriceFields()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => ['gross' => 1000, 'net' => 1000],
            ],
        ], $versionContext);

        /** @var ProductStruct $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals(100, $product->getPrice()->getGross());
        static::assertEquals(10, $product->getPrice()->getNet());

        /** @var ProductStruct $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals(1000, $product->getPrice()->getGross());
        static::assertEquals(1000, $product->getPrice()->getNet());

        $this->productRepository->merge($versionId, $context);

        /** @var ProductStruct $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals(1000, $product->getPrice()->getGross());
        static::assertEquals(1000, $product->getPrice()->getNet());
    }

    public function testICanVersionDateFields()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
            'releaseDate' => '2018-01-01',
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'releaseDate' => '2018-10-05',
            ],
        ], $versionContext);

        /** @var ProductStruct $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals('2018-01-01', $product->getReleaseDate()->format('Y-m-d'));

        $product = $this->productRepository->read(new ReadCriteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals('2018-10-05', $product->getReleaseDate()->format('Y-m-d'));

        $this->productRepository->merge($versionId, $context);
        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertEquals('2018-10-05', $product->getReleaseDate()->format('Y-m-d'));
    }

    public function testICanVersionObjectFields()
    {
        static::markTestIncomplete('Object fields are not supported currently');
    }

    public function testICanVersionCalculatedFields()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $id3 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id1,
            'name' => 'category-1',
            'children' => [
                [
                    'id' => $id2,
                    'name' => 'category-2',
                    'children' => [
                        [
                            'id' => $id3,
                            'name' => 'category-3',
                        ],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create([$data], $context);

        $versionId = $this->categoryRepository->createVersion($id3, $context);

        $versionContext = $context->createWithVersionId($versionId);

        /** @var CategoryStruct $category */
        $category = $this->categoryRepository->read(new ReadCriteria([$id3]), $versionContext)->first();
        static::assertInstanceOf(CategoryStruct::class, $category);
        static::assertEquals('|' . $id2 . '|' . $id1 . '|', $category->getPath());
    }

    public function testICanVersionTranslatedFields()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $changelog = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::LANGUAGE_EN, 'productId', $id, $context->getVersionId());
        static::assertCount(1, $changelog);
        static::assertArrayHasKey('name', $changelog[0]['payload']);
        static::assertEquals('test', $changelog[0]['payload']['name']);

        $this->productRepository->update([['id' => $id, 'name' => 'updated']], $context);
        $changelog = $this->getTranslationVersionData(ProductTranslationDefinition::getEntityName(), Defaults::LANGUAGE_EN, 'productId', $id, $context->getVersionId());
        static::assertCount(2, $changelog);
        static::assertArrayHasKey('name', $changelog[1]['payload']);
        static::assertEquals('updated', $changelog[1]['payload']['name']);
    }

    public function testChangelogWrittenForCreate()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(1, $changelog);
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
    }

    public function testChangelogWrittenForUpdate()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(2, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        //check update written
        static::assertEquals($id, $changelog[1]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[1]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[1]['entity_name']);
        static::assertEquals('upsert', $changelog[1]['action']);
    }

    public function testChangelogWrittenWithMultipleEntities()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['id' => $id, 'name' => 'create'],
            'tax' => ['id' => $id, 'name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(1, $changelog);
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        $changelog = $this->getVersionData('product_manufacturer', $id, $context->getVersionId());

        static::assertCount(1, $changelog);
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product_manufacturer', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        $changelog = $this->getVersionData('tax', $id, $context->getVersionId());

        static::assertCount(1, $changelog);
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('tax', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);
    }

    public function testChangelogAppliedAfterMerge()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(1, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        $changelog = $this->getVersionData('product', $id, $versionId);

        static::assertCount(1, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('clone', $changelog[0]['action']);

        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $changelog = $this->getVersionData('product', $id, $versionId);

        static::assertCount(2, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('clone', $changelog[0]['action']);

        static::assertEquals($id, $changelog[1]['entity_id']['id']);
        static::assertEquals($versionId, $changelog[1]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[1]['entity_name']);
        static::assertEquals('upsert', $changelog[1]['action']);

        static::assertArrayHasKey('payload', $changelog[1]);
        static::assertArrayHasKey('ean', $changelog[1]['payload']);
        static::assertEquals('updated', $changelog[1]['payload']['ean']);

        $this->productRepository->merge($versionId, $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());
        static::assertCount(2, $changelog);

        //check insert written
        static::assertEquals($id, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        static::assertEquals($id, $changelog[1]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[1]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[1]['entity_name']);
        static::assertEquals('upsert', $changelog[1]['action']);

        static::assertArrayHasKey('payload', $changelog[1]);
        static::assertArrayHasKey('ean', $changelog[1]['payload']);
        static::assertEquals('updated', $changelog[1]['payload']['ean']);
    }

    public function testICanVersionOneToManyAssociations()
    {
        $productId = Uuid::uuid4()->getHex();
        $ruleId = Uuid::uuid4()->getHex();
        $priceId1 = Uuid::uuid4()->getHex();
        $priceId2 = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
        ], $context);

        $product = [
            'id' => $productId,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $priceId1,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 15, 'net' => 10],
                ],
                [
                    'id' => $priceId2,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => ['gross' => 10, 'net' => 8],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        //check both products exists
        $products = $this->connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(2, $products);

        $versions = array_map(function ($item) {
            return Uuid::fromBytesToHex($item['version_id']);
        }, $products);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $prices = $this->connection->fetchAll('SELECT * FROM product_price_rule WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(4, $prices);

        $versionPrices = array_filter($prices, function (array $price) use ($versionId) {
            $version = Uuid::fromBytesToHex($price['version_id']);

            return $version === $versionId;
        });

        static::assertCount(2, $versionPrices);
        foreach ($versionPrices as $price) {
            $productVersionId = Uuid::fromBytesToHex($price['product_version_id']);
            static::assertEquals($versionId, $productVersionId);
        }
    }

    public function testICanVersionManyToManyAssociations()
    {
        $productId = Uuid::uuid4()->getHex();
        $categoryId1 = Uuid::uuid4()->getHex();
        $categoryId2 = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        //check both products exists
        $products = $this->connection->fetchAll('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(2, $products);

        $versions = array_map(function ($item) {
            return Uuid::fromBytesToHex($item['version_id']);
        }, $products);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $categories = $this->connection->fetchAll('SELECT * FROM product_category WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(4, $categories);
    }

    public function testICanReadASpecifyVersion()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        /** @var ProductStruct $product */
        $product = $this->productRepository->read(new ReadCriteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertSame('updated', $product->getEan());

        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertSame('EAN', $product->getEan());

        $this->productRepository->merge($versionId, $context);
        $product = $this->productRepository->read(new ReadCriteria([$id]), $context)->first();
        static::assertInstanceOf(ProductStruct::class, $product);
        static::assertSame('updated', $product->getEan());
    }

    public function testICanReadOneToManyInASpecifyVersion()
    {
    }

    public function testICanReadManyTOManyInASpecifyVersion()
    {
    }

    public function testReadConsidersLiveVersionAsFallbackForOneToMany()
    {
    }

    public function testReadConsidersLiveVersionAsFallbackForManyToMany()
    {
    }

    public function testICanSearchInASpecifyVersion()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => ['gross' => 1000, 'net' => 1000],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price.gross', 1000));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(0, $products);

        $products = $this->productRepository->search($criteria, $versionContext);
        static::assertCount(1, $products);
    }

    public function testICanSearchOneToManyInASpecifyVersion()
    {
    }

    public function testICanSearchManyToManyInASpecifyVersion()
    {
    }

    public function testSearchConsidersLiveVersionAsFallback()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $data = [
            [
                'id' => $id1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => ['gross' => 100, 'net' => 10],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'name' => 'test',
                'price' => ['gross' => 100, 'net' => 10],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = $this->productRepository->createVersion($id2, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([['id' => $id2, 'ean' => 'EAN']], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(1, $products);

        //in this version we have two products with the ean value "EAN"
        $products = $this->productRepository->search($criteria, $versionContext);
        static::assertCount(2, $products);
    }

    public function testSearchConsidersLiveVersionAsFallbackForOneToMany()
    {
    }

    public function testSearchConsidersLiveVersionAsFallbackForManyToMany()
    {
    }

    public function testICanAggregateInASpecifyVersion()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => ['gross' => 100, 'net' => 10],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => ['gross' => 1000, 'net' => 1000],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('product.price.gross', 'sum_price'));

        /** @var SumAggregationResult $sum */
        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(100, $sum->getSum());

        /** @var SumAggregationResult $sum */
        $aggregations = $this->productRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(1000, $sum->getSum());
    }

    public function testICanAggregateOneToManyInASpecifyVersion()
    {
    }

    public function testICanAggregateManyToManyInASpecifyVersion()
    {
    }

    public function testAggregateConsidersLiveVersionAsFallback()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();
        $data = [
            [
                'id' => $id1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => ['gross' => 100, 'net' => 10],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => ['gross' => 100, 'net' => 10],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = $this->productRepository->createVersion($id1, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id1,
                'price' => ['gross' => 900, 'net' => 900],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('product.price.gross', 'sum_price'));

        /** @var SumAggregationResult $sum */
        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(200, $sum->getSum());

        /** @var SumAggregationResult $sum */
        $aggregations = $this->productRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->getAggregations()->has('sum_price'));
        $sum = $aggregations->getAggregations()->get('sum_price');
        static::assertEquals(1000, $sum->getSum());
    }

    public function testAggregateConsidersLiveVersionAsFallbackForOneToMany()
    {
    }

    public function testAggregateConsidersLiveVersionAsFallbackForManyToMany()
    {
    }

    public function testICanAddEntitiesToSpecifyVersion()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $data = [
            [
                'id' => $id1,
                'name' => 'test',
                'ean' => 'EAN-1',
                'price' => ['gross' => 100, 'net' => 10],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'name' => 'test',
                'ean' => 'EAN-2',
                'price' => ['gross' => 100, 'net' => 10],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = Uuid::uuid4()->getHex();
        $this->productRepository->createVersion($id1, $context, 'campaign', $versionId);
        $this->productRepository->createVersion($id2, $context, 'campaign', $versionId);

        //check changelog written for product 1
        $changelog = $this->getVersionData('product', $id1, $context->getVersionId());
        static::assertCount(1, $changelog);
        static::assertEquals($id1, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        //check changelog written for product 2 with same version
        $changelog = $this->getVersionData('product', $id2, $context->getVersionId());
        static::assertCount(1, $changelog);
        static::assertEquals($id2, $changelog[0]['entity_id']['id']);
        static::assertEquals($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertEquals('product', $changelog[0]['entity_name']);
        static::assertEquals('insert', $changelog[0]['action']);

        //update products of specify version
        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->update(
            [
                ['id' => $id1, 'ean' => 'EAN-1-update'],
                ['id' => $id2, 'ean' => 'EAN-2-update'],
            ],
            $versionContext
        );

        $products = $this->productRepository->read(new ReadCriteria([$id1, $id2]), $versionContext);
        //check both products updated
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertEquals('EAN-1-update', $products->get($id1)->getEan());
        static::assertEquals('EAN-2-update', $products->get($id2)->getEan());

        //check existing live version not to be updated
        $products = $this->productRepository->read(new ReadCriteria([$id1, $id2]), $context);
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertEquals('EAN-1', $products->get($id1)->getEan());
        static::assertEquals('EAN-2', $products->get($id2)->getEan());

        //do merge
        $this->productRepository->merge($versionId, $context);

        //check both products are merged
        $products = $this->productRepository->read(new ReadCriteria([$id1, $id2]), $context);
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertEquals('EAN-1-update', $products->get($id1)->getEan());
        static::assertEquals('EAN-2-update', $products->get($id2)->getEan());
    }

    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        $data = $this->connection->fetchAll(
            "SELECT d.* 
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity 
             AND JSON_EXTRACT(entity_id, '$.id') = :id
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );

        $data = array_map(function (array $row) {
            $row['entity_id'] = json_decode($row['entity_id'], true);
            $row['payload'] = json_decode($row['payload'], true);

            return $row;
        }, $data);

        return $data;
    }

    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId): array
    {
        $data = $this->connection->fetchAll(
            "SELECT * 
             FROM version_commit_data 
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, '$." . $foreignKeyName . "') = :id
             AND JSON_EXTRACT(entity_id, '$.languageId') = :language
             AND JSON_EXTRACT(entity_id, '$.versionId') = :version
             ORDER BY auto_increment",
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId,
            ]
        );

        $data = array_map(function (array $row) {
            $row['entity_id'] = json_decode($row['entity_id'], true);
            $row['payload'] = json_decode($row['payload'], true);

            return $row;
        }, $data);

        return $data;
    }
}
