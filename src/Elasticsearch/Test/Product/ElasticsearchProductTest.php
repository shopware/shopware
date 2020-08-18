<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Product;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util\DateHistogramCase;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchRegistry;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class ElasticsearchProductTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;
    use FilesystemBehaviour;
    use CacheTestBehaviour;
    use BasicTestDataBehaviour;
    use SessionTestBehaviour;
    use QueueTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var Connection|object|null
     */
    private $connection;

    /**
     * @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository
     */
    private $productRepository;

    /**
     * @var object|\Shopware\Core\Framework\DataAbstractionLayer\EntityRepository|null
     */
    private $salesChannelRepository;

    /**
     * @var string
     */
    private $navigationId;

    protected function setUp(): void
    {
        $this->helper = $this->getContainer()->get(ElasticsearchHelper::class);
        $this->client = $this->getContainer()->get(Client::class);
        $this->productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $this->languageRepository = $this->getContainer()->get('language.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->navigationId = $this->connection->fetchColumn(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL)]
        );

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $elasticsearchRegistry = $this->getContainer()->get(ElasticsearchRegistry::class);

        $extension = new ElasticsearchProductDefinitionExtension(
            $this->getContainer()->get(ProductDefinition::class),
            $this->getContainer()->get(EntityMapper::class)
        );

        $rulesProperty = ReflectionHelper::getProperty(ElasticsearchRegistry::class, 'definitions');
        $rulesProperty->setValue($elasticsearchRegistry, [$extension]);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->removeExtension(ProductExtension::class);

        parent::tearDown();
    }

    /**
     * @beforeClass
     */
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->executeUpdate('
            DROP TABLE IF EXISTS `extended_product`;
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `product_id` BINARY(16) NULL,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            )
        ');

        $connection->beginTransaction();
    }

    /**
     * @afterClass
     */
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
        $connection->executeUpdate('DROP TABLE `extended_product`');
    }

    public function testIndexing()
    {
        $this->connection->executeUpdate('DELETE FROM product');

        $context = Context::createDefaultContext();

        //Instead of indexing the test data in the set-up, we index it in the first test method. So this data does not have to be indexed again in each test.
        $this->ids = new TestDataCollection($context);

        $this->createData();

        $this->indexElasticSearch();

        $products = $this->ids->prefixed('p');

        $languages = $this->languageRepository->searchIds(new Criteria(), $context);

        foreach ($languages->getIds() as $languageId) {
            $index = $this->helper->getIndexName($this->productDefinition, $languageId);

            $exists = $this->client->indices()->exists(['index' => $index]);
            static::assertTrue($exists);

            foreach ($products as $id) {
                $exists = $this->client->exists(['index' => $index, 'id' => $id]);
                static::assertTrue($exists, 'Product with id ' . $id . ' missing');
            }
        }

        return $this->ids;
    }

    /**
     * @depends testIndexing
     */
    public function testUpdate(TestDataCollection $ids): void
    {
        $this->ids = $ids;
        $context = Context::createDefaultContext();

        $this->productRepository->upsert([
            $this->createProduct('u7', 'update', 't3', 'm3', 300, '2021-12-10 11:59:00', 200, 300, []),
        ], $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', 'u7'));

        // products should be updated immediately
        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(1, $result->getIds());

        $this->productRepository->delete([['id' => $ids->get('u7')]], $context);
        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(0, $result->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testEmptySearch(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(count($data->prefixed('p')), $products->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testPagination(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();

        // check pagination
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->setLimit(1);

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(count($data->prefixed('p')), $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testEqualsFilter(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        // check simple equals filter
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addFilter(new EqualsFilter('stock', 2));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testRangeFilter(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        // check simple range filter
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addFilter(new RangeFilter('product.stock', [RangeFilter::GTE => 10]));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(5, $products->getIds());
        static::assertSame(5, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testEqualsAnyFilter(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        // check filter for categories
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addFilter(new EqualsAnyFilter('product.categoriesRo.id', [$data->get('c1')]));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(3, $products->getIds());
        static::assertSame(3, $products->getTotal());
        static::assertContains($data->get('p1'), $products->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testContainsFilter(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('product.name', 'tilk'));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
        static::assertContains($data->get('p3'), $products->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('product.name', 'subber'));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(0, $products->getIds());
        static::assertSame(0, $products->getTotal());

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('product.name', 'Rubb'));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
        static::assertContains($data->get('p2'), $products->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('product.name', 'bber'));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
        static::assertContains($data->get('p2'), $products->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testSingleGroupBy(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        // check simple equals filter
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addGroupField(new FieldGrouping('stock'));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(4, $products->getIds());
        static::assertContains($data->get('p1'), $products->getIds());
        static::assertContains($data->get('p2'), $products->getIds());
        static::assertContains($data->get('p3'), $products->getIds());
        static::assertTrue(
            in_array($data->get('p4'), $products->getIds(), true)
            || in_array($data->get('p5'), $products->getIds(), true)
            || in_array($data->get('p6'), $products->getIds(), true)
        );
    }

    /**
     * @depends testIndexing
     */
    public function testMultiGroupBy(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        // check simple equals filter
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addGroupField(new FieldGrouping('stock'));
        $criteria->addGroupField(new FieldGrouping('purchasePrice'));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(5, $products->getIds());
        static::assertContains($data->get('p1'), $products->getIds());
        static::assertContains($data->get('p2'), $products->getIds());
        static::assertContains($data->get('p3'), $products->getIds());
        static::assertContains($data->get('p6'), $products->getIds());

        static::assertTrue(
            in_array($data->get('p4'), $products->getIds(), true)
            || in_array($data->get('p5'), $products->getIds(), true)
        );
    }

    /**
     * @depends testIndexing
     */
    public function testAvgAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new AvgAggregation('avg-price', 'product.price'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('avg-price'));

        /** @var AvgResult $result */
        $result = $aggregations->get('avg-price');
        static::assertInstanceOf(AvgResult::class, $result);

        static::assertEquals(175, $result->getAvg());
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new TermsAggregation('manufacturer-ids', 'product.manufacturerId'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregationWithAvg(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new AvgAggregation('avg-price', 'product.price'))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');

        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());

        /** @var AvgResult $price */
        $price = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $price);
        static::assertEquals(50, $price->getAvg());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $price);
        static::assertEquals(150, $price->getAvg());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());

        $price = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $price);
        static::assertEquals(275, $price->getAvg());
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregationWithAssociation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new TermsAggregation('manufacturer-ids', 'product.manufacturer.id'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregationWithLimit(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturer.id', 2, new FieldSorting('product.manufacturer.name'))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(2, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregationWithSorting(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturer.id', null, new FieldSorting('product.manufacturer.name', FieldSorting::DESCENDING))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        $ordered = $data->getList(['m3', 'm2', 'm1']);
        static::assertEquals(array_values($ordered), $result->getKeys());

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturer.id', null, new FieldSorting('product.manufacturer.name', FieldSorting::ASCENDING))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        $ordered = $data->getList(['m1', 'm2', 'm3']);
        static::assertEquals(array_values($ordered), $result->getKeys());
    }

    /**
     * @depends testIndexing
     */
    public function testSumAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new SumAggregation('sum-price', 'product.price'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('sum-price'));

        /** @var SumResult $result */
        $result = $aggregations->get('sum-price');
        static::assertInstanceOf(SumResult::class, $result);

        static::assertEquals(1050, $result->getSum());
    }

    /**
     * @depends testIndexing
     */
    public function testSumAggregationWithTermsAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new SumAggregation('price-sum', 'product.price'))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());
        /** @var SumResult $price */
        $price = $bucket->getResult();
        static::assertInstanceOf(SumResult::class, $price);
        static::assertEquals(50, $price->getSum());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(SumResult::class, $price);
        static::assertEquals(450, $price->getSum());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(SumResult::class, $price);
        static::assertEquals(550, $price->getSum());
    }

    /**
     * @depends testIndexing
     */
    public function testMaxAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new MaxAggregation('max-price', 'product.price'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('max-price'));

        /** @var MaxResult $result */
        $result = $aggregations->get('max-price');
        static::assertInstanceOf(MaxResult::class, $result);

        static::assertEquals(300, $result->getMax());
    }

    /**
     * @depends testIndexing
     */
    public function testMaxAggregationWithTermsAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new MaxAggregation('price-max', 'product.price'))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());
        /** @var MaxResult $price */
        $price = $bucket->getResult();
        static::assertInstanceOf(MaxResult::class, $price);
        static::assertEquals(50, $price->getMax());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(MaxResult::class, $price);
        static::assertEquals(200, $price->getMax());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(MaxResult::class, $price);
        static::assertEquals(300, $price->getMax());
    }

    /**
     * @depends testIndexing
     */
    public function testMinAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new MinAggregation('min-price', 'product.price'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('min-price'));

        /** @var MinResult $result */
        $result = $aggregations->get('min-price');
        static::assertInstanceOf(MinResult::class, $result);

        static::assertEquals(50, $result->getMin());
    }

    /**
     * @depends testIndexing
     */
    public function testMinAggregationWithTermsAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new MinAggregation('price-min', 'product.price'))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());
        /** @var MinResult $price */
        $price = $bucket->getResult();
        static::assertInstanceOf(MinResult::class, $price);
        static::assertEquals(50, $price->getMin());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(MinResult::class, $price);
        static::assertEquals(100, $price->getMin());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(MinResult::class, $price);
        static::assertEquals(250, $price->getMin());
    }

    /**
     * @depends testIndexing
     */
    public function testCountAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new CountAggregation('manufacturer-count', 'product.manufacturerId'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-count'));

        /** @var CountResult $result */
        $result = $aggregations->get('manufacturer-count');
        static::assertInstanceOf(CountResult::class, $result);

        static::assertEquals(6, $result->getCount());
    }

    /**
     * @depends testIndexing
     */
    public function testCountAggregationWithTermsAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new CountAggregation('price-count', 'product.price'))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());
        /** @var CountResult $price */
        $price = $bucket->getResult();
        static::assertInstanceOf(CountResult::class, $price);
        static::assertEquals(1, $price->getCount());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(CountResult::class, $price);
        static::assertEquals(3, $price->getCount());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(CountResult::class, $price);
        static::assertEquals(2, $price->getCount());
    }

    /**
     * @depends testIndexing
     */
    public function testStatsAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new StatsAggregation('price-stats', 'product.price'));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('price-stats'));

        /** @var StatsResult $result */
        $result = $aggregations->get('price-stats');
        static::assertInstanceOf(StatsResult::class, $result);

        static::assertEquals(50, $result->getMin());
        static::assertEquals(300, $result->getMax());
        static::assertEquals(175, $result->getAvg());
        static::assertEquals(1050, $result->getSum());
    }

    /**
     * @depends testIndexing
     */
    public function testStatsAggregationWithTermsAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new StatsAggregation('price-stats', 'product.price'))
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturer-ids'));

        /** @var TermsResult $result */
        $result = $aggregations->get('manufacturer-ids');
        static::assertInstanceOf(TermsResult::class, $result);

        static::assertCount(3, $result->getBuckets());

        static::assertContains($data->get('m1'), $result->getKeys());
        static::assertContains($data->get('m2'), $result->getKeys());
        static::assertContains($data->get('m3'), $result->getKeys());

        $bucket = $result->get($data->get('m1'));
        static::assertEquals(1, $bucket->getCount());
        /** @var StatsResult $price */
        $price = $bucket->getResult();
        static::assertInstanceOf(StatsResult::class, $price);
        static::assertEquals(50, $price->getSum());
        static::assertEquals(50, $price->getMax());
        static::assertEquals(50, $price->getMin());
        static::assertEquals(50, $price->getAvg());

        $bucket = $result->get($data->get('m2'));
        static::assertEquals(3, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(StatsResult::class, $price);
        static::assertEquals(450, $price->getSum());
        static::assertEquals(200, $price->getMax());
        static::assertEquals(100, $price->getMin());
        static::assertEquals(150, $price->getAvg());

        $bucket = $result->get($data->get('m3'));
        static::assertEquals(2, $bucket->getCount());
        $price = $bucket->getResult();
        static::assertInstanceOf(StatsResult::class, $price);
        static::assertEquals(550, $price->getSum());
        static::assertEquals(300, $price->getMax());
        static::assertEquals(250, $price->getMin());
        static::assertEquals(275, $price->getAvg());
    }

    /**
     * @depends testIndexing
     */
    public function testEntityAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(new EntityAggregation('manufacturers', 'product.manufacturerId', ProductManufacturerDefinition::ENTITY_NAME));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturers'));

        /** @var EntityResult $result */
        $result = $aggregations->get('manufacturers');
        static::assertInstanceOf(EntityResult::class, $result);

        static::assertCount(3, $result->getEntities());

        static::assertTrue($result->getEntities()->has($data->get('m1')));
        static::assertTrue($result->getEntities()->has($data->get('m2')));
        static::assertTrue($result->getEntities()->has($data->get('m3')));
    }

    /**
     * @depends testIndexing
     */
    public function testEntityAggregationWithTermQuery(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = (new Criteria($data->prefixed('p')))->setTerm('Grouped');
        $criteria->addAggregation(new EntityAggregation('manufacturers', 'product.manufacturerId', ProductManufacturerDefinition::ENTITY_NAME));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('manufacturers'));

        /** @var EntityResult $result */
        $result = $aggregations->get('manufacturers');
        static::assertInstanceOf(EntityResult::class, $result);

        static::assertCount(2, $result->getEntities());

        static::assertTrue($result->getEntities()->has($data->get('m2')));
        static::assertTrue($result->getEntities()->has($data->get('m3')));
    }

    /**
     * @depends testIndexing
     */
    public function testTermAlgorithm(TestDataCollection $data): void
    {
        $terms = ['Spachtelmasse', 'Spachtel', 'Masse', 'Achtel', 'Some', 'some spachtel', 'Some Achtel', 'Sachtel'];

        $searcher = $this->createEntitySearcher();

        foreach ($terms as $term) {
            $criteria = new Criteria();
            $criteria->setTerm($term);

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertEquals(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
            static::assertTrue($products->has($data->get('p6')));

            $term = strtolower($term);
            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertEquals(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
            static::assertTrue($products->has($data->get('p6')));

            $term = strtoupper($term);
            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertEquals(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
            static::assertTrue($products->has($data->get('p6')));
        }
    }

    /**
     * @depends testIndexing
     */
    public function testFilterAggregation(TestDataCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addAggregation(
            new FilterAggregation(
                'filter',
                new AvgAggregation('avg-price', 'product.price'),
                [new EqualsAnyFilter('product.id', $data->getList(['p1', 'p2']))]
            )
        );

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(1, $aggregations);

        static::assertTrue($aggregations->has('avg-price'));

        /** @var AvgResult $result */
        $result = $aggregations->get('avg-price');
        static::assertInstanceOf(AvgResult::class, $result);

        static::assertEquals(75, $result->getAvg());
    }

    /**
     * @depends testIndexing
     * @dataProvider dateHistogramProvider
     */
    public function testDateHistogram(DateHistogramCase $case, TestDataCollection $data): void
    {
        $context = Context::createDefaultContext();

        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));

        $criteria->addAggregation(
            new DateHistogramAggregation(
                'release-histogram',
                'product.releaseDate',
                $case->getInterval(),
                null,
                null,
                $case->getFormat()
            )
        );

        $result = $aggregator->aggregate($this->productDefinition, $criteria, $context);

        static::assertTrue($result->has('release-histogram'));

        /** @var DateHistogramResult|null $histogram */
        $histogram = $result->get('release-histogram');
        static::assertInstanceOf(DateHistogramResult::class, $histogram);

        static::assertCount(count($case->getBuckets()), $histogram->getBuckets(), print_r($histogram->getBuckets(), true));

        foreach ($case->getBuckets() as $key => $count) {
            static::assertTrue($histogram->has($key));
            $bucket = $histogram->get($key);
            static::assertSame($count, $bucket->getCount());
        }
    }

    public function dateHistogramProvider()
    {
        return [
            [new DateHistogramCase(DateHistogramAggregation::PER_MINUTE, [
                '2019-01-01 10:11:00' => 1,
                '2019-01-01 10:13:00' => 1,
                '2019-06-15 13:00:00' => 1,
                '2020-09-30 15:00:00' => 1,
                '2021-12-10 11:59:00' => 2,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_HOUR, [
                '2019-01-01 10:00:00' => 2,
                '2019-06-15 13:00:00' => 1,
                '2020-09-30 15:00:00' => 1,
                '2021-12-10 11:00:00' => 2,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                '2019-01-01 00:00:00' => 2,
                '2019-06-15 00:00:00' => 1,
                '2020-09-30 00:00:00' => 1,
                '2021-12-10 00:00:00' => 2,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_WEEK, [
                '2018 01' => 2,
                '2019 24' => 1,
                '2020 40' => 1,
                '2021 49' => 2,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
                '2019-01-01 00:00:00' => 2,
                '2019-06-01 00:00:00' => 1,
                '2020-09-01 00:00:00' => 1,
                '2021-12-01 00:00:00' => 2,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_QUARTER, [
                '2019 1' => 2,
                '2019 2' => 1,
                '2020 3' => 1,
                '2021 4' => 2,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_YEAR, [
                '2019-01-01 00:00:00' => 3,
                '2020-01-01 00:00:00' => 1,
                '2021-01-01 00:00:00' => 2,
            ])],
            [new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
                '2019 January' => 2,
                '2019 June' => 1,
                '2020 September' => 1,
                '2021 December' => 2,
            ], 'Y F')],
            [new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                'Tuesday 01st Jan, 2019' => 2,
                'Saturday 15th Jun, 2019' => 1,
                'Wednesday 30th Sep, 2020' => 1,
                'Friday 10th Dec, 2021' => 2,
            ], 'l dS M, Y')],
        ];
    }

    /**
     * @depends testIndexing
     */
    public function testDateHistogramWithNestedAvg(TestDataCollection $data): void
    {
        $context = Context::createDefaultContext();

        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria($data->prefixed('p'));

        $criteria->addAggregation(
            new DateHistogramAggregation(
                'release-histogram',
                'product.releaseDate',
                DateHistogramAggregation::PER_MONTH,
                null,
                new AvgAggregation('price', 'product.price')
            )
        );

        $result = $aggregator->aggregate($this->productDefinition, $criteria, $context);

        static::assertTrue($result->has('release-histogram'));

        /** @var DateHistogramResult|null $histogram */
        $histogram = $result->get('release-histogram');
        static::assertInstanceOf(DateHistogramResult::class, $histogram);

        static::assertCount(4, $histogram->getBuckets());

        $bucket = $histogram->get('2019-01-01 00:00:00');
        static::assertInstanceOf(Bucket::class, $bucket);
        /** @var AvgResult $price */
        $price = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $price);
        static::assertEquals(75, $price->getAvg());

        $bucket = $histogram->get('2019-06-01 00:00:00');
        static::assertInstanceOf(Bucket::class, $bucket);
        $price = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $price);
        static::assertEquals(150, $price->getAvg());

        $bucket = $histogram->get('2020-09-01 00:00:00');
        static::assertInstanceOf(Bucket::class, $bucket);
        $price = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $price);
        static::assertEquals(200, $price->getAvg());

        $bucket = $histogram->get('2021-12-01 00:00:00');
        static::assertInstanceOf(Bucket::class, $bucket);
        $price = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $price);
        static::assertEquals(275, $price->getAvg());
    }

    /**
     * @depends testIndexing
     */
    public function testExtensionFilter(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        // check simple equals filter
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addFilter(new EqualsFilter('toOne.name', 'test'));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testFilterCustomTextField(TestDataCollection $data): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.testField', 'Silk'));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertEquals(1, $result->getTotal());
        static::assertTrue($result->has($data->get('p1')));
    }

    /**
     * @depends testIndexing
     */
    public function testXorQuery(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();

        $criteria = new Criteria();

        $multiFilter = new MultiFilter(
            MultiFilter::CONNECTION_XOR,
            [
                new EqualsFilter('taxId', $data->get('t1')),
                new EqualsFilter('manufacturerId', $data->get('m2')),
            ]
        );
        $criteria->addFilter($multiFilter);

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertSame(3, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testNegativXorQuery(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();

        $criteria = new Criteria();

        $multiFilter = new MultiFilter(
            MultiFilter::CONNECTION_XOR,
            [
                new EqualsFilter('taxId', 'foo'),
                new EqualsFilter('manufacturerId', 'baa'),
            ]
        );
        $criteria->addFilter($multiFilter);

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertSame(0, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testTotalWithGroupFieldAndPostFilter(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();
        // check simple equals filter
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addGroupField(new FieldGrouping('stock'));
        $criteria->addPostFilter(new EqualsFilter('manufacturerId', $data->get('m2')));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

        static::assertEquals(3, $products->getTotal());
        static::assertCount(3, $products->getIds());
        static::assertContains($data->get('p2'), $products->getIds());
        static::assertContains($data->get('p3'), $products->getIds());
        static::assertContains($data->get('p4'), $products->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testIdsSorting(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();

        $expected = [
            $data->get('p2'),
            $data->get('p3'),
            $data->get('p1'),
            $data->get('p4'),
            $data->get('p5'),
        ];

        // check simple equals filter
        $criteria = new Criteria($expected);

        $criteria->addFilter(new RangeFilter('stock', [
            RangeFilter::GTE => 0,
        ]));

        $ids = $searcher->search($this->productDefinition, $criteria, $data->getContext());

        static::assertEquals($expected, $ids->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testSorting(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();

        $expected = [
            $data->get('p4'),
            $data->get('p5'),
            $data->get('p2'),
            $data->get('p1'),
            $data->get('p6'),
            $data->get('p3'),
        ];

        // check simple equals filter
        $criteria = new Criteria($data->prefixed('p'));
        $criteria->addSorting(new FieldSorting('name'));

        $ids = $searcher->search($this->productDefinition, $criteria, $data->getContext());

        static::assertEquals($expected, $ids->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testMaxLimit(TestDataCollection $data): void
    {
        $searcher = $this->createEntitySearcher();

        // check simple equals filter
        $criteria = new Criteria($data->getList(['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'n7', 'n8', 'n9', 'n10', 'n11']));

        $ids = $searcher->search($this->productDefinition, $criteria, $data->getContext());

        static::assertCount(11, $ids->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testStorefrontListing(TestDataCollection $data): void
    {
        $this->helper->setEnabled(true);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $request = new Request();

        $result = $this->getContainer()->get(ProductListingRoute::class)
            ->load($context->getSalesChannel()->getNavigationCategoryId(), $request, $context, new Criteria());

        $listing = $result->getResult();

        static::assertTrue($listing->getTotal() > 0);
        static::assertTrue($listing->getAggregations()->has('shipping-free'));
        static::assertTrue($listing->getAggregations()->has('rating'));
        static::assertTrue($listing->getAggregations()->has('price'));
        static::assertTrue($listing->getAggregations()->has('options'));
        static::assertTrue($listing->getAggregations()->has('properties'));
        static::assertTrue($listing->getAggregations()->has('manufacturer'));
    }

    /**
     * @depends testIndexing
     */
    public function testSortingIsCaseInsensitive(TestDataCollection $data): void
    {
        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('categoriesRo.id', $data->get('cs1')));
        $criteria->addSorting(new FieldSorting('name'));

        $searcher = $this->createEntitySearcher();
        $ids = $searcher->search($this->productDefinition, $criteria, Context::createDefaultContext())->getIds();

        // 3 products per letter
        $idList = array_chunk($ids, 3);

        // Cause the product names are lowercased: Aa, AA, aa is the same for elastic. We can't determine the right order
        // So we split the ids the first 3 should be one of aa products, last 3 should be some of Bb
        static::assertContains($data->get('s1'), $idList[0]);
        static::assertContains($data->get('s2'), $idList[0]);
        static::assertContains($data->get('s3'), $idList[0]);

        static::assertContains($data->get('s4'), $idList[1]);
        static::assertContains($data->get('s5'), $idList[1]);
        static::assertContains($data->get('s6'), $idList[1]);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    private function createProduct(
        string $key,
        string $name,
        string $taxKey,
        string $manufacturerKey,
        float $price,
        string $releaseDate,
        float $purchasePrice,
        int $stock,
        array $categoryKeys,
        array $extensions = []
    ): array {
        $categories = array_map(function ($categoryKey) {
            return ['id' => $this->ids->create($categoryKey), 'name' => $categoryKey];
        }, $categoryKeys);

        $data = [
            'id' => $this->ids->create($key),
            'productNumber' => $key,
            'name' => $name,
            'stock' => $stock,
            'purchasePrice' => $purchasePrice,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / 115 * 100, 'linked' => false],
            ],
            'manufacturer' => ['id' => $this->ids->create($manufacturerKey), 'name' => $manufacturerKey],
            'tax' => ['id' => $this->ids->create($taxKey),  'name' => 'test', 'taxRate' => 15],
            'releaseDate' => $releaseDate,
            'customFields' => [
                'testField' => $name,
            ],
            'visibilities' => [
                ['salesChannelId' => Defaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $categories[] = ['id' => $this->navigationId];
        $data['categories'] = $categories;

        foreach ($extensions as $extensionKey => $extension) {
            $data[$extensionKey] = $extension;
        }

        return $data;
    }

    private function createData(): void
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('product.repository');

        $repo->create([
            $this->createProduct('p1', 'Silk', 't1', 'm1', 50, '2019-01-01 10:11:00', 0, 2, ['c1', 'c2'], ['toOne' => [
                'name' => 'test',
            ]]),
            $this->createProduct('p2', 'Rubber', 't1', 'm2', 100, '2019-01-01 10:13:00', 0, 10, ['c1']),
            $this->createProduct('p3', 'Stilk', 't2', 'm2', 150, '2019-06-15 13:00:00', 100, 100, ['c1', 'c3']),
            $this->createProduct('p4', 'Grouped 1', 't2', 'm2', 200, '2020-09-30 15:00:00', 100, 300, ['c3']),
            $this->createProduct('p5', 'Grouped 2', 't3', 'm3', 250, '2021-12-10 11:59:00', 100, 300, []),
            $this->createProduct('p6', 'Spachtelmasse of some company', 't3', 'm3', 300, '2021-12-10 11:59:00', 200, 300, []),
            $this->createProduct('n7', 'Other product', 't3', 'm3', 300, '2021-12-10 11:59:00', 200, 300, []),
            $this->createProduct('n8', 'Other product', 't3', 'm3', 300, '2021-12-10 11:59:00', 200, 300, []),
            $this->createProduct('n9', 'Other product', 't3', 'm3', 300, '2021-12-10 11:59:00', 200, 300, []),
            $this->createProduct('n10', 'Other product', 't3', 'm3', 300, '2021-12-10 11:59:00', 200, 300, []),
            $this->createProduct('n11', 'Other product', 't3', 'm3', 300, '2021-12-10 11:59:00', 200, 300, []),
            $this->createProduct('s1', 'aa', 't1', 'm2', 100, '2019-01-01 10:13:00', 0, 10, ['cs1']),
            $this->createProduct('s2', 'Aa', 't1', 'm2', 100, '2019-01-01 10:13:00', 0, 10, ['cs1']),
            $this->createProduct('s3', 'AA', 't1', 'm2', 100, '2019-01-01 10:13:00', 0, 10, ['cs1']),
            $this->createProduct('s4', 'Ba', 't1', 'm2', 100, '2019-01-01 10:13:00', 0, 10, ['cs1']),
            $this->createProduct('s5', 'BA', 't1', 'm2', 100, '2019-01-01 10:13:00', 0, 10, ['cs1']),
            $this->createProduct('s6', 'BB', 't1', 'm2', 100, '2019-01-01 10:13:00', 0, 10, ['cs1']),
        ], Context::createDefaultContext());
    }
}
