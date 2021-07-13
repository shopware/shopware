<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Product;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util\DateHistogramCase;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group skip-paratest
 */
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

    private Client $client;

    private ?object $productDefinition;

    private ?object $languageRepository;

    private ?object $helper;

    private ?IdsCollection $ids;

    private ?object $connection;

    private ?object $productRepository;

    private string $navigationId;

    private string $currencyId = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    private string $anotherCurrencyId = '2c962ddb7b3346f29c748a9d3b884302';

    private ElasticsearchProductDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = $this->getContainer()->get(ElasticsearchProductDefinition::class);
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

        $this->ids = new IdsCollection();
        $this->ids->set('navi', $this->navigationId);

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
        try {
            $this->connection->executeUpdate('DELETE FROM product');

            $context = Context::createDefaultContext();
            $context->addState(Context::STATE_ELASTICSEARCH_AWARE);

            $this->client->indices()->delete(['index' => '_all']);
            $this->client->indices()->refresh(['index' => '_all']);

            $this->ids->getContext()->addState(Context::STATE_ELASTICSEARCH_AWARE);
            $this->ids->set('currency', $this->currencyId);
            $this->ids->set('anotherCurrency', $this->anotherCurrencyId);
            $currencies = [
                [
                    'id' => $this->currencyId,
                    'name' => 'test',
                    'factor' => 1,
                    'symbol' => 'A',
                    'decimalPrecision' => 2,
                    'shortName' => 'A',
                    'isoCode' => 'A',
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true)), true),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true)), true),
                ],
                [
                    'id' => $this->anotherCurrencyId,
                    'name' => 'test',
                    'factor' => 0.001,
                    'symbol' => 'B',
                    'decimalPrecision' => 2,
                    'shortName' => 'B',
                    'isoCode' => 'B',
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true)), true),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true)), true),
                ],
            ];

            $this->getContainer()
                ->get('currency.repository')
                ->upsert($currencies, Context::createDefaultContext());

            $this->createData();

            $this->indexElasticSearch();

            $languages = $this->languageRepository->searchIds(new Criteria(), $context);

            foreach ($languages->getIds() as $languageId) {
                $index = $this->helper->getIndexName($this->productDefinition, $languageId);

                $exists = $this->client->indices()->exists(['index' => $index]);
                static::assertTrue($exists, 'Expected elasticsearch indices present');
            }

            return $this->ids;
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testUpdate(IdsCollection $ids): void
    {
        try {
            $this->ids = $ids;
            $context = Context::createDefaultContext();
            $context->addState(Context::STATE_ELASTICSEARCH_AWARE);

            $this->productRepository->upsert([
                (new ProductBuilder($this->ids, 'u7', 300))
                    ->price(100)
                    ->build(),
            ], $context);

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('productNumber', 'u7'));

            // products should be updated immediately
            $result = $this->productRepository->searchIds($criteria, $context);
            static::assertCount(1, $result->getIds());

            $this->productRepository->delete([['id' => $ids->get('u7')]], $context);
            $result = $this->productRepository->searchIds($criteria, $context);
            static::assertCount(0, $result->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testEmptySearch(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(\count($data->prefixed('product-')), $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testPagination(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            // check pagination
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->setLimit(1);

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(\count($data->prefixed('product-')), $products->getTotal());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testEqualsFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addFilter(new EqualsFilter('stock', 2));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testRangeFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple range filter
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addFilter(new RangeFilter('product.stock', [RangeFilter::GTE => 10]));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(6, $products->getIds());
            static::assertSame(6, $products->getTotal());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testEqualsAnyFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check filter for categories
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addFilter(new EqualsAnyFilter('product.categoriesRo.id', [$data->get('c1')]));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(3, $products->getIds());
            static::assertSame(3, $products->getTotal());
            static::assertContains($data->get('product-1'), $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testMultiNotFilterFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check filter for categories
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [
                        new RangeFilter('product.price', [RangeFilter::LTE => 101]),
                        new ContainsFilter('product.name', 'ilk'),
                    ]
                )
            );

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(6, $products->getIds());
            static::assertSame(6, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());
            static::assertContains($data->get('product-3'), $products->getIds());
            static::assertContains($data->get('product-4'), $products->getIds());
            static::assertContains($data->get('product-5'), $products->getIds());
            static::assertContains($data->get('product-6'), $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testContainsFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            $criteria = new Criteria();
            $criteria->addFilter(new ContainsFilter('product.name', 'tilk'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-3'), $products->getIds());

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
            static::assertContains($data->get('product-2'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addFilter(new ContainsFilter('product.name', 'bber'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testPrefixFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            $criteria = new Criteria();
            $criteria->addFilter(new PrefixFilter('product.name', 'Sti'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-3'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addFilter(new PrefixFilter('product.name', 'subber'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(0, $products->getIds());
            static::assertSame(0, $products->getTotal());

            $criteria = new Criteria();
            $criteria->addFilter(new PrefixFilter('product.name', 'Rubb'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addFilter(new PrefixFilter('product.name', 'Spacht'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-6'), $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSuffixFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            $criteria = new Criteria();
            $criteria->addFilter(new SuffixFilter('product.name', 'tilk'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-3'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addFilter(new SuffixFilter('product.name', 'subber'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(0, $products->getIds());
            static::assertSame(0, $products->getTotal());

            $criteria = new Criteria();
            $criteria->addFilter(new SuffixFilter('product.name', 'bber'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addFilter(new SuffixFilter('product.name', 'company'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-6'), $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSingleGroupBy(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addGroupField(new FieldGrouping('stock'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(5, $products->getIds());
            static::assertContains($data->get('product-1'), $products->getIds());
            static::assertContains($data->get('product-2'), $products->getIds());
            static::assertContains($data->get('product-3'), $products->getIds());
            static::assertTrue(
                \in_array($data->get('product-4'), $products->getIds(), true)
                || \in_array($data->get('product-5'), $products->getIds(), true)
                || \in_array($data->get('product-6'), $products->getIds(), true)
            );
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testMultiGroupBy(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addGroupField(new FieldGrouping('stock'));
            $criteria->addGroupField(new FieldGrouping('purchasePrices'));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(6, $products->getIds());
            static::assertContains($data->get('product-1'), $products->getIds());
            static::assertContains($data->get('product-2'), $products->getIds());
            static::assertContains($data->get('product-3'), $products->getIds());
            static::assertContains($data->get('product-6'), $products->getIds());

            static::assertTrue(
                \in_array($data->get('product-4'), $products->getIds(), true)
                || \in_array($data->get('product-5'), $products->getIds(), true)
            );
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testAvgAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addAggregation(new AvgAggregation('avg-price', 'product.price'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('avg-price'));

            /** @var AvgResult $result */
            $result = $aggregations->get('avg-price');
            static::assertInstanceOf(AvgResult::class, $result);

            static::assertEquals(192.85714285714, $result->getAvg());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregationWithAvg(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testTermsAggregationWithAssociation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSumAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addAggregation(new SumAggregation('sum-price', 'product.price'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('sum-price'));

            /** @var SumResult $result */
            $result = $aggregations->get('sum-price');
            static::assertInstanceOf(SumResult::class, $result);

            static::assertEquals(1350, $result->getSum());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSumAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testMaxAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testMaxAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testMinAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testMinAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testCountAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testCountAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testStatsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addAggregation(new StatsAggregation('price-stats', 'product.price'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('price-stats'));

            /** @var StatsResult $result */
            $result = $aggregations->get('price-stats');
            static::assertInstanceOf(StatsResult::class, $result);

            static::assertEquals(50, $result->getMin());
            static::assertEquals(300, $result->getMax());
            static::assertEquals(192.85714285714, $result->getAvg());
            static::assertEquals(1350, $result->getSum());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testStatsAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testEntityAggregation(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testEntityAggregationWithTermQuery(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testTermAlgorithm(IdsCollection $data): void
    {
        try {
            $terms = ['Spachtelmasse', 'Spachtel', 'Masse', 'Achtel', 'Some', 'some spachtel', 'Some Achtel', 'Sachtel'];

            $searcher = $this->createEntitySearcher();

            foreach ($terms as $term) {
                $criteria = new Criteria();
                $criteria->setTerm($term);

                $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

                static::assertEquals(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));

                $term = strtolower($term);
                $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
                static::assertEquals(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));

                $term = strtoupper($term);
                $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
                static::assertEquals(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testFilterAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addAggregation(
                new FilterAggregation(
                    'filter',
                    new AvgAggregation('avg-price', 'product.price'),
                    [new EqualsAnyFilter('product.id', $data->getList(['product-1', 'product-2']))]
                )
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('avg-price'));

            /** @var AvgResult $result */
            $result = $aggregations->get('avg-price');
            static::assertInstanceOf(AvgResult::class, $result);

            static::assertEquals(75, $result->getAvg());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testFilterForProperties(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check filter for categories
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addFilter(new EqualsAnyFilter('product.properties.id', [$data->get('red')]));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(2, $products->getIds());
            static::assertTrue($products->has($data->get('product-1')));
            static::assertTrue($products->has($data->get('product-3')));
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends      testIndexing
     * @dataProvider dateHistogramProvider
     */
    public function testDateHistogram(DateHistogramCase $case, IdsCollection $data): void
    {
        try {
            $context = Context::createDefaultContext();
            $context->addState(Context::STATE_ELASTICSEARCH_AWARE);

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
                    $case->getFormat(),
                    $case->getTimeZone()
                )
            );

            $result = $aggregator->aggregate($this->productDefinition, $criteria, $context);

            static::assertTrue($result->has('release-histogram'));

            /** @var DateHistogramResult|null $histogram */
            $histogram = $result->get('release-histogram');
            static::assertInstanceOf(DateHistogramResult::class, $histogram);

            static::assertCount(\count($case->getBuckets()), $histogram->getBuckets(), print_r($histogram->getBuckets(), true));

            foreach ($case->getBuckets() as $key => $count) {
                static::assertTrue($histogram->has($key));
                $bucket = $histogram->get($key);
                static::assertSame($count, $bucket->getCount());
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    public function dateHistogramProvider()
    {
        return [
            [
                new DateHistogramCase(DateHistogramAggregation::PER_MINUTE, [
                    '2019-01-01 10:11:00' => 1,
                    '2019-01-01 10:13:00' => 1,
                    '2019-06-15 13:00:00' => 1,
                    '2020-09-30 15:00:00' => 1,
                    '2021-12-10 11:59:00' => 2,
                    '2024-12-11 23:59:00' => 1,
                ]),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_HOUR, [
                    '2019-01-01 10:00:00' => 2,
                    '2019-06-15 13:00:00' => 1,
                    '2020-09-30 15:00:00' => 1,
                    '2021-12-10 11:00:00' => 2,
                    '2024-12-11 23:00:00' => 1,
                ]),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                    '2019-01-01 00:00:00' => 2,
                    '2019-06-15 00:00:00' => 1,
                    '2020-09-30 00:00:00' => 1,
                    '2021-12-10 00:00:00' => 2,
                    '2024-12-11 00:00:00' => 1,
                ]),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_WEEK, [
                    '2018 01' => 2,
                    '2019 24' => 1,
                    '2020 40' => 1,
                    '2021 49' => 2,
                    '2024 50' => 1,
                ]),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
                    '2019-01-01 00:00:00' => 2,
                    '2019-06-01 00:00:00' => 1,
                    '2020-09-01 00:00:00' => 1,
                    '2021-12-01 00:00:00' => 2,
                    '2024-12-01 00:00:00' => 1,
                ]),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_QUARTER, [
                    '2019 1' => 2,
                    '2019 2' => 1,
                    '2020 3' => 1,
                    '2021 4' => 2,
                    '2024 4' => 1,
                ]),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_YEAR, [
                    '2019-01-01 00:00:00' => 3,
                    '2020-01-01 00:00:00' => 1,
                    '2021-01-01 00:00:00' => 2,
                    '2024-01-01 00:00:00' => 1,
                ]),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_MONTH, [
                    '2019 January' => 2,
                    '2019 June' => 1,
                    '2020 September' => 1,
                    '2021 December' => 2,
                    '2024 December' => 1,
                ], 'Y F'),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                    'Tuesday 01st Jan, 2019' => 2,
                    'Saturday 15th Jun, 2019' => 1,
                    'Wednesday 30th Sep, 2020' => 1,
                    'Friday 10th Dec, 2021' => 2,
                    'Wednesday 11th Dec, 2024' => 1,
                ], 'l dS M, Y'),
            ],
            [
                new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                    '2019-01-01 00:00:00' => 2,
                    '2019-06-15 00:00:00' => 1,
                    '2020-09-30 00:00:00' => 1,
                    '2021-12-10 00:00:00' => 2,
                    '2024-12-12 00:00:00' => 1,
                ], null, 'Europe/Berlin'),
            ],
        ];
    }

    /**
     * @depends testIndexing
     */
    public function testDateHistogramWithNestedAvg(IdsCollection $data): void
    {
        try {
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

            $result = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());

            static::assertTrue($result->has('release-histogram'));

            /** @var DateHistogramResult|null $histogram */
            $histogram = $result->get('release-histogram');
            static::assertInstanceOf(DateHistogramResult::class, $histogram);

            static::assertCount(5, $histogram->getBuckets());

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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testFilterPurchasePricesPriceField(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            // Filter by the PriceField purchasePrices
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('purchasePrices', 100));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
            static::assertCount(3, $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testFilterCustomTextField(IdsCollection $data): void
    {
        try {
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addFilter(new EqualsFilter('customFields.testField', 'Silk'));

            $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

            static::assertEquals(1, $result->getTotal());
            static::assertTrue($result->has($data->get('product-1')));
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testXorQuery(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            $criteria = new Criteria($data->prefixed('product-'));

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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testNegativXorQuery(IdsCollection $data): void
    {
        try {
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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testTotalWithGroupFieldAndPostFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addGroupField(new FieldGrouping('stock'));
            $criteria->addPostFilter(new EqualsFilter('manufacturerId', $data->get('m2')));

            $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertEquals(3, $products->getTotal());
            static::assertCount(3, $products->getIds());
            static::assertContains($data->get('product-2'), $products->getIds());
            static::assertContains($data->get('product-3'), $products->getIds());
            static::assertContains($data->get('product-4'), $products->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testIdsSorting(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            $expected = [
                $data->get('product-2'),
                $data->get('product-3'),
                $data->get('product-1'),
                $data->get('product-4'),
                $data->get('product-5'),
            ];

            // check simple equals filter
            $criteria = new Criteria($expected);

            $criteria->addFilter(new RangeFilter('stock', [
                RangeFilter::GTE => 0,
            ]));

            $ids = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertEquals($expected, $ids->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSorting(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            $expected = [
                $data->get('product-4'),
                $data->get('product-5'),
                $data->get('product-2'),
                $data->get('product-1'),
                $data->get('product-6'),
                $data->get('product-3'),
                $data->get('product-7'),
            ];

            // check simple equals filter
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addSorting(new FieldSorting('name'));

            $ids = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertEquals($expected, $ids->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testMaxLimit(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            // check simple equals filter
            $criteria = new Criteria($data->getList(['product-1', 'product-2', 'product-3', 'product-4', 'product-5', 'product-6', 'n7', 'n8', 'n9', 'n10', 'n11']));

            $ids = $searcher->search($this->productDefinition, $criteria, $data->getContext());

            static::assertCount(11, $ids->getIds());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testStorefrontListing(): void
    {
        try {
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
            static::assertTrue($listing->getAggregations()->has('properties'));
            static::assertTrue($listing->getAggregations()->has('manufacturer'));
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSortingIsCaseInsensitive(IdsCollection $data): void
    {
        try {
            $criteria = new Criteria();

            $criteria->addFilter(new EqualsFilter('categoriesRo.id', $data->get('cs1')));
            $criteria->addSorting(new FieldSorting('name'));

            $searcher = $this->createEntitySearcher();
            $ids = $searcher->search($this->productDefinition, $criteria, $data->getContext())->getIds();

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
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testCheapestPriceFilter(IdsCollection $ids): void
    {
        try {
            $cases = $this->providerCheapestPriceFilter();

            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
            $context->getContext()->addState(Context::STATE_ELASTICSEARCH_AWARE);

            $searcher = $this->createEntitySearcher();

            foreach ($cases as $message => $case) {
                $affected = array_merge(
                    $ids->prefixed('p.'),
                    $ids->prefixed('v.')
                );
                $criteria = new Criteria(array_values($affected));

                $criteria->addFilter(
                    new RangeFilter('product.cheapestPrice', [
                        RangeFilter::GTE => $case['from'],
                        RangeFilter::LTE => $case['to'],
                    ])
                );

                $context->setRuleIds([]);
                if (isset($case['rules'])) {
                    $context->setRuleIds($ids->getList($case['rules']));
                }

                $result = $searcher->search($this->productDefinition, $criteria, $context->getContext());

                static::assertCount(\count($case['expected']), $result->getIds(), $message . ' failed');

                foreach ($case['expected'] as $key) {
                    static::assertTrue($result->has($ids->get($key)), sprintf('Missing id %s in case `%s`', $key, $message));
                }
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    public function providerCheapestPriceFilter()
    {
        yield 'Test 70€ filter without rule' => ['from' => 70, 'to' => 71, 'expected' => ['p.1', 'v.4.2']];
        yield 'Test 79€ filter without rule' => ['from' => 79, 'to' => 80, 'expected' => ['v.2.1', 'v.2.2']];
        yield 'Test 90€ filter without rule' => ['from' => 90, 'to' => 91, 'expected' => ['v.3.1']];
        yield 'Test 60€ filter without rule' => ['from' => 60, 'to' => 61, 'expected' => ['v.4.1']];
        yield 'Test 110€ filter without rule' => ['from' => 110, 'to' => 111, 'expected' => ['p.5']];
        yield 'Test 120€ filter without rule' => ['from' => 120, 'to' => 121, 'expected' => ['v.6.1', 'v.6.2']];
        yield 'Test 130€ filter without rule' => ['from' => 130, 'to' => 131, 'expected' => ['v.7.1', 'v.7.2']];
        yield 'Test 140€ filter without rule' => ['from' => 140, 'to' => 141, 'expected' => ['v.8.1', 'v.8.2']];
        yield 'Test 150€ filter/10 without rule' => ['from' => 150, 'to' => 151, 'expected' => ['v.9.1', 'v.10.2']];
        yield 'Test 170€ filter without rule' => ['from' => 170, 'to' => 171, 'expected' => ['v.11.1', 'v.11.2']];
        yield 'Test 180€ filter without rule' => ['from' => 180, 'to' => 181, 'expected' => ['v.12.1', 'v.12.2']];
        yield 'Test 190€ filter without rule' => ['from' => 190, 'to' => 191, 'expected' => ['v.13.1', 'v.13.2']];
        yield 'Test 70€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 70, 'to' => 71, 'expected' => ['p.1', 'v.4.2']];
        yield 'Test 79€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 79, 'to' => 80, 'expected' => ['v.2.1', 'v.2.2']];
        yield 'Test 90€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 90, 'to' => 91, 'expected' => ['v.3.1']];
        yield 'Test 60€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 60, 'to' => 61, 'expected' => ['v.4.1']];
        yield 'Test 130€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 130, 'to' => 131, 'expected' => ['v.6.1']];
        yield 'Test 140€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 140, 'to' => 141, 'expected' => ['v.6.2', 'v.7.2']];
        yield 'Test 150€ filter/10 with rule-a' => ['rules' => ['rule-a'], 'from' => 150, 'to' => 151, 'expected' => ['v.7.1', 'v.10.2']];
        yield 'Test 170€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 170, 'to' => 171, 'expected' => ['v.8.2']];
        yield 'Test 160€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 160, 'to' => 161, 'expected' => ['v.8.1', 'v.9.1', 'v.9.2', 'v.10.1']];
        yield 'Test 210€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 210, 'to' => 211, 'expected' => ['v.12.1', 'v.13.2']];
        yield 'Test 220€ filter with rule-a' => ['rules' => ['rule-a'], 'from' => 220, 'to' => 221, 'expected' => ['v.13.1']];
        yield 'Test 70€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 70, 'to' => 71, 'expected' => ['p.1', 'v.4.2']];
        yield 'Test 79€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 79, 'to' => 80, 'expected' => ['v.2.1', 'v.2.2']];
        yield 'Test 90€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 90, 'to' => 91, 'expected' => ['v.3.1']];
        yield 'Test 60€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 60, 'to' => 61, 'expected' => ['v.4.1']];
        yield 'Test 130€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 130, 'to' => 131, 'expected' => ['v.6.1']];
        yield 'Test 140€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 140, 'to' => 141, 'expected' => ['v.6.2', 'v.7.2']];
        yield 'Test 150€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 150, 'to' => 151, 'expected' => ['v.7.1', 'v.10.2']];
        yield 'Test 170€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 170, 'to' => 171, 'expected' => ['v.8.2']];
        yield 'Test 160€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 160, 'to' => 161, 'expected' => ['v.8.1', 'v.9.1', 'v.9.2', 'v.10.1']];
        yield 'Test 200€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 200, 'to' => 201, 'expected' => ['v.13.2']];
        yield 'Test 180€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 180, 'to' => 181, 'expected' => ['v.12.1']];
        yield 'Test 190€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 190, 'to' => 191, 'expected' => ['v.11.1', 'v.11.2', 'v.12.2', 'v.13.1']];
    }

    /**
     * @depends testIndexing
     */
    public function testCheapestPriceSorting(IdsCollection $ids): void
    {
        try {
            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
            $context->getContext()->addState(Context::STATE_ELASTICSEARCH_AWARE);

            $cases = $this->providerCheapestPriceSorting();

            foreach ($cases as $message => $case) {
                $context->setRuleIds($ids->getList($case['rules']));

                $this->assertSorting($message, $ids, $context, $case, FieldSorting::ASCENDING);

                $this->assertSorting($message, $ids, $context, $case, FieldSorting::DESCENDING);
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    public function providerCheapestPriceSorting()
    {
        yield 'Test sorting without rules' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.1', 'v.7.2', 'v.8.1', 'v.8.2', 'v.10.2', 'v.9.1', 'v.10.1', 'v.9.2', 'v.11.1', 'v.11.2', 'v.12.1', 'v.12.2', 'v.13.1', 'v.13.2'],
            'rules' => [],
        ];

        yield 'Test sorting with rule a' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.2', 'v.10.2', 'v.7.1', 'v.10.1', 'v.8.1', 'v.9.1', 'v.9.2', 'v.8.2', 'v.11.1', 'v.11.2', 'v.12.2', 'v.12.1', 'v.13.2', 'v.13.1'],
            'rules' => ['rule-a'],
        ];

        yield 'Test sorting with rule b' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.1', 'v.7.2', 'v.8.1', 'v.8.2', 'v.10.2', 'v.9.1', 'v.10.1', 'v.9.2', 'v.12.1', 'v.11.1', 'v.11.2', 'v.12.2', 'v.13.1', 'v.13.2'],
            'rules' => ['rule-b'],
        ];

        yield 'Test sorting with rule a+b' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.2', 'v.10.2', 'v.7.1', 'v.10.1', 'v.8.1', 'v.9.1', 'v.9.2', 'v.8.2', 'v.11.1', 'v.11.2', 'v.12.2', 'v.12.1', 'v.13.2', 'v.13.1'],
            'rules' => ['rule-a', 'rule-b'],
        ];

        yield 'Test sorting with rule b+a' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.2', 'v.10.2', 'v.7.1', 'v.10.1', 'v.8.1', 'v.9.1', 'v.9.2', 'v.8.2', 'v.12.1', 'v.11.1', 'v.11.2', 'v.12.2', 'v.13.1', 'v.13.2'],
            'rules' => ['rule-b', 'rule-a'],
        ];
    }

    /**
     * @depends testIndexing
     */
    public function testCheapestPriceAggregation(IdsCollection $ids): void
    {
        $context = $ids->getContext();

        try {
            $affected = array_merge(
                $ids->prefixed('p.'),
                $ids->prefixed('v.')
            );
            $criteria = new Criteria(array_values($affected));
            $criteria->addFilter(new OrFilter([
                new NandFilter([new EqualsFilter('product.parentId', null)]),
                new EqualsFilter('product.childCount', 0),
            ]));

            $criteria->addAggregation(new StatsAggregation('price', 'product.cheapestPrice'));

            $aggregator = $this->createEntityAggregator();

            $cases = $this->providerCheapestPriceAggregation();

            foreach ($cases as $message => $case) {
                $context->setRuleIds($ids->getList($case['rules']));

                $result = $aggregator->aggregate($this->productDefinition, $criteria, $context);

                $aggregation = $result->get('price');

                static::assertInstanceOf(StatsResult::class, $aggregation);
                static::assertEquals($case['min'], $aggregation->getMin(), sprintf('Case `%s` failed', $message));
                static::assertEquals($case['max'], $aggregation->getMax(), sprintf('Case `%s` failed', $message));
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testLanguageFieldsWorkSimilarToDAL(IdsCollection $ids): void
    {
        $context = $this->createIndexingContext();

        // Fetch: Default language
        $products = $this->definition->fetch([$ids->getBytes('dal-1')], $context);

        $product = $products[$ids->get('dal-1')];

        $p = $this->getContainer()->get('product.repository')->search(new Criteria([$ids->get('dal-1')]), $context)->first();

        static::assertSame((string) $p->getTranslation('name'), $product['name']);
        static::assertSame((string) $p->getTranslation('description'), $product['description']);
        static::assertSame($p->getTranslation('customFields'), $product['customFields']);

        // Fetch: Second language
        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$ids->get('language-1')]);
        $languageContext->addExtensions($context->getExtensions());
        $products = $this->definition->fetch([$ids->getBytes('dal-1')], $languageContext);

        $product = $products[$ids->get('dal-1')];

        $p = $this->fetchProductFromDAL($ids, $languageContext);

        static::assertSame((string) $p->getTranslation('name'), $product['name']);
        static::assertSame((string) $p->getTranslation('description'), $product['description']);
        static::assertSame($p->getTranslation('customFields'), $product['customFields']);

        // Fetch: Third language
        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$ids->get('language-2'), $ids->get('language-1')]);
        $languageContext->addExtensions($context->getExtensions());
        $products = $this->definition->fetch([$ids->getBytes('dal-1')], $languageContext);

        $product = $products[$ids->get('dal-1')];

        $p = $this->fetchProductFromDAL($ids, $languageContext);

        static::assertSame((string) $p->getTranslation('name'), $product['name']);
        static::assertSame((string) $p->getTranslation('description'), $product['description']);
        static::assertSame($p->getTranslation('customFields'), $product['customFields']);
    }

    /**
     * @depends testIndexing
     */
    public function testReleaseDate(IdsCollection $ids): void
    {
        $products = $this->definition->fetch([$ids->getBytes('dal-1')], $this->createIndexingContext());

        $product = $products[$ids->get('dal-1')];

        static::assertSame('2019-01-01T10:11:00+00:00', $product['releaseDate']);
    }

    /**
     * @depends testIndexing
     */
    public function testProductSizeWidthHeightStockSales(IdsCollection $ids): void
    {
        $products = $this->definition->fetch([$ids->getBytes('dal-1')], $this->createIndexingContext());

        $product = $products[$ids->get('dal-1')];

        static::assertSame(12.3, $product['weight']);
        static::assertSame(9.3, $product['height']);
        static::assertSame(1.3, $product['width']);
        static::assertSame(2, $product['stock']);
        static::assertSame(0, $product['sales']);
    }

    /**
     * @depends testIndexing
     */
    public function testCategoriesProperties(IdsCollection $ids): void
    {
        $products = $this->definition->fetch([$ids->getBytes('dal-1')], $this->createIndexingContext());

        $product = $products[$ids->get('dal-1')];
        $categoryIds = array_column($product['categoriesRo'], 'id');

        static::assertContains($ids->get('c1'), $categoryIds);
        static::assertContains($ids->get('c2'), $categoryIds);

        static::assertContains($ids->get('red'), $product['propertyIds']);
        static::assertContains($ids->get('xl'), $product['propertyIds']);
    }

    /**
     * @depends testIndexing
     */
    public function testCustomFieldsGetMapped(IdsCollection $ids): void
    {
        $ref = new \ReflectionClass($this->definition);
        $property = $ref->getProperty('customFieldsTypes');
        $property->setAccessible(true);
        $property->setValue($this->definition, null);

        $mapping = $this->definition->getMapping($ids->getContext());

        $expected = [
            'type' => 'object',
            'dynamic' => true,
            'properties' => [
                'test_bool' => [
                    'type' => 'boolean',
                ],
                'test_date' => [
                    'type' => 'date',
                    'format' => 'yyyy-MM-dd HH:mm:ss.000',
                    'ignore_malformed' => true,
                ],
                'test_float' => [
                    'type' => 'double',
                ],
                'test_int' => [
                    'type' => 'long',
                ],
                'test_object' => [
                    'type' => 'object',
                    'dynamic' => true,
                ],
                'test_string' => [
                    'type' => 'keyword',
                ],
                'test_unmapped' => [
                    'type' => 'keyword',
                ],
            ],
        ];

        static::assertEquals($expected, $mapping['properties']['customFields']);
    }

    /**
     * @depends testIndexing
     */
    public function testSortByCustomFieldInt(IdsCollection $ids): void
    {
        $context = $ids->getContext();

        try {
            $criteria = new Criteria();
            $criteria->addSorting(new FieldSorting('customFields.test_int', FieldSorting::DESCENDING));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('product-1'), $result[0]);
            static::assertSame($ids->get('product-2'), $result[1]);
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSortByCustomFieldDate(IdsCollection $ids): void
    {
        $context = $ids->getContext();

        try {
            $criteria = new Criteria();
            $criteria->addSorting(new FieldSorting('customFields.test_date', FieldSorting::DESCENDING));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('product-1'), $result[0]);
            static::assertSame($ids->get('product-2'), $result[1]);
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testSortByCurrencyPrice(IdsCollection $ids): void
    {
        $context = $ids->getContext();

        try {
            $criteria = new Criteria();
            $criteria->addSorting(new FieldSorting(sprintf('price.%s.gross', $this->anotherCurrencyId), FieldSorting::DESCENDING));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('product-3'), $result[0]);
            static::assertSame($ids->get('product-5'), $result[1]);
            static::assertSame($ids->get('product-4'), $result[2]);
            static::assertSame($ids->get('product-2'), $result[3]);
            static::assertSame($ids->get('product-6'), $result[4]);
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testFetchFloatedCustomFieldIds(IdsCollection $ids): void
    {
        $context = $ids->getContext();

        try {
            $criteria = new Criteria();
            $criteria->addAggregation(new TermsAggregation('testFloatingField', 'customFields.testFloatingField'));

            $aggregator = $this->createEntityAggregator();

            /** @var TermsResult $result */
            $result = $aggregator->aggregate($this->productDefinition, $criteria, $context)->get('testFloatingField');

            static::assertContains('1', $result->getKeys());
            static::assertContains('1.5', $result->getKeys());
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @depends testIndexing
     */
    public function testFilterByCustomFieldDate(IdsCollection $ids): void
    {
        $context = $ids->getContext();

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customFields.test_date', '2000-01-01 00:00:00.000'));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('product-2'), $result[0]);
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    private function assertSorting(string $message, IdsCollection $ids, SalesChannelContext $context, array $case, string $direction): void
    {
        $criteria = new Criteria(array_merge(
            $ids->prefixed('p.'),
            $ids->prefixed('v.'),
        ));

        $criteria->addSorting(new FieldSorting('product.cheapestPrice', $direction));
        $criteria->addSorting(new FieldSorting('product.productNumber', $direction));

        $criteria->addFilter(new OrFilter([
            new NandFilter([new EqualsFilter('product.parentId', null)]),
            new EqualsFilter('product.childCount', 0),
        ]));

        $searcher = $this->createEntitySearcher();
        $result = $searcher->search($this->productDefinition, $criteria, $context->getContext());

        $expected = $case['ids'];
        if ($direction === FieldSorting::DESCENDING) {
            $expected = array_reverse($expected);
        }

        $actual = array_values($result->getIds());

        foreach ($expected as $index => $key) {
            $id = $actual[$index];
            static::assertEquals($ids->get($key), $id, sprintf('Case `%s` failed for %s', $message, $key));
        }
    }

    private function providerCheapestPriceAggregation()
    {
        yield 'With no rules' => ['min' => 60, 'max' => 190, 'rules' => []];
        yield 'With rule a' => ['min' => 60, 'max' => 220, 'rules' => ['rule-a']];
        yield 'With rule b' => ['min' => 60, 'max' => 200, 'rules' => ['rule-b']];
        yield 'With rule a+b' => ['min' => 60, 'max' => 220, 'rules' => ['rule-a', 'rule-b']];
        yield 'With rule b+a' => ['min' => 60, 'max' => 200, 'rules' => ['rule-b', 'rule-a']];
    }

    private function createData(): void
    {
        $secondLanguage = $this->createLanguage();
        $this->ids->set('language-1', $secondLanguage);
        $thirdLanguage = $this->createLanguage($secondLanguage);
        $this->ids->set('language-2', $thirdLanguage);

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM custom_field');

        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'name' => 'swag_example_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'English custom field set label',
                        'de-DE' => 'German custom field set label',
                    ],
                ],
                'relations' => [[
                    'entityName' => 'product',
                ]],
                'customFields' => [
                    [
                        'name' => 'test_int',
                        'type' => CustomFieldTypes::INT,
                    ],
                    [
                        'name' => 'test_string',
                        'type' => CustomFieldTypes::TEXT,
                    ],
                    [
                        'name' => 'test_date',
                        'type' => CustomFieldTypes::DATETIME,
                    ],
                    [
                        'name' => 'test_object',
                        'type' => CustomFieldTypes::JSON,
                    ],
                    [
                        'name' => 'test_float',
                        'type' => CustomFieldTypes::FLOAT,
                    ],
                    [
                        'name' => 'test_bool',
                        'type' => CustomFieldTypes::BOOL,
                    ],
                    [
                        'name' => 'test_unmapped',
                        'type' => 'unknown_type',
                    ],
                ],
            ],
        ], $this->ids->getContext());

        $products = [
            (new ProductBuilder($this->ids, 'product-1'))
                ->name('Silk')
                ->category('navi')
                ->customField('testField', 'Silk')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m1')
                ->price(50)
                ->releaseDate('2019-01-01 10:11:00')
                ->purchasePrice(0)
                ->stock(2)
                ->category('c1')
                ->category('c2')
                ->property('red', 'color')
                ->property('xl', 'size')
                ->customField('test_int', 19999)
                ->customField('test_date', (new \DateTime())->format('Y-m-d H:i:s'))
                ->customField('testFloatingField', 1.5)
                ->customField('test_bool', true)
                ->build(),
            (new ProductBuilder($this->ids, 'product-2'))
                ->name('Rubber')
                ->category('navi')
                ->customField('testField', 'Rubber')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->price(300, null, 'anotherCurrency')
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('c1')
                ->property('green', 'color')
                ->property('l', 'size')
                ->customField('test_int', 200)
                ->customField('test_date', (new \DateTime('2000-01-01'))->format('Y-m-d H:i:s'))
                ->customField('testFloatingField', 1) // Without the casting in formatCustomFields this fails
                ->build(),
            (new ProductBuilder($this->ids, 'product-3'))
                ->name('Stilk')
                ->category('navi')
                ->customField('testField', 'Stilk')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t2')
                ->manufacturer('m2')
                ->price(150)
                ->price(800, null, 'anotherCurrency')
                ->releaseDate('2019-06-15 13:00:00')
                ->purchasePrice(100)
                ->stock(100)
                ->category('c1')
                ->category('c3')
                ->property('red', 'color')
                ->build(),
            (new ProductBuilder($this->ids, 'product-4'))
                ->name('Grouped 1')
                ->category('navi')
                ->customField('testField', 'Grouped 1')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t2')
                ->manufacturer('m2')
                ->price(200)
                ->price(500, null, 'anotherCurrency')
                ->releaseDate('2020-09-30 15:00:00')
                ->purchasePrice(100)
                ->stock(300)
                ->property('green', 'color')
                ->build(),
            (new ProductBuilder($this->ids, 'product-5'))
                ->name('Grouped 2')
                ->category('navi')
                ->customField('testField', 'Grouped 2')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(250)
                ->price(600, null, 'anotherCurrency')
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(100)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 'product-6'))
                ->name('Spachtelmasse of some awesome company')
                ->category('navi')
                ->customField('testField', 'Spachtelmasse')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->price(200, null, 'anotherCurrency')
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 'product-7'))
                ->name('Test Product for Timezone ReleaseDate')
                ->category('navi')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->price(300)
                ->releaseDate('2024-12-11 23:59:00')
                ->stock(350)
                ->build(),
            (new ProductBuilder($this->ids, 'n7'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 'n8'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 'n9'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 'n10'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 'n11'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(300)
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(200)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 's1'))
                ->name('aa')
                ->category('navi')
                ->customField('testField', 'aa')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($this->ids, 's2'))
                ->name('Aa')
                ->category('navi')
                ->customField('testField', 'Aa')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($this->ids, 's3'))
                ->name('AA')
                ->category('navi')
                ->customField('testField', 'AA')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($this->ids, 's4'))
                ->name('Ba')
                ->category('navi')
                ->customField('testField', 'Ba')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($this->ids, 's5'))
                ->name('BA')
                ->category('navi')
                ->customField('testField', 'BA')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($this->ids, 's6'))
                ->name('BB')
                ->category('navi')
                ->customField('testField', 'BB')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),

            // no rule = 70€
            (new ProductBuilder($this->ids, 'p.1'))
                ->price(70)
                ->price(99, null, 'currency')
                ->visibility(Defaults::SALES_CHANNEL)
                ->build(),

            // no rule = 79€
            (new ProductBuilder($this->ids, 'p.2'))
                ->price(80)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.2.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.2.2'))
                        ->price(79)
                        ->price(88, null, 'currency')
                        ->build()
                )
                ->build(),

            // no rule = 90€
            (new ProductBuilder($this->ids, 'p.3'))
                ->price(90)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.3.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.3.2'))
                        ->price(100)
                        ->build()
                )
                ->build(),

            // no rule = 60€
            (new ProductBuilder($this->ids, 'p.4'))
                ->price(100)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.4.1'))
                        ->price(60)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.4.2'))
                        ->price(70)
                        ->price(101, null, 'currency')
                        ->build()
                )
                ->build(),

            // no rule = 110€  ||  rule-a = 130€
            (new ProductBuilder($this->ids, 'p.5'))
                ->price(110)
                ->prices('rule-a', 130)
                ->prices('rule-a', 120, 'default', null, 3)
                ->visibility(Defaults::SALES_CHANNEL)
                ->build(),

            // no rule = 120€  ||  rule-a = 130€
            (new ProductBuilder($this->ids, 'p.6'))
                ->price(120)
                ->prices('rule-a', 150)
                ->prices('rule-a', 140, 'default', null, 3)
                ->prices('rule-a', 199, 'currency')
                ->prices('rule-a', 188, 'currency', null, 3)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.6.1'))
                        ->prices('rule-a', 140)
                        ->prices('rule-a', 130, 'default', null, 3)
                        ->prices('rule-a', 188, 'currency')
                        ->prices('rule-a', 177, 'currency', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.6.2'))
                        ->build()
                )
                ->build(),

            // no rule = 130€  ||   rule-a = 150€
            (new ProductBuilder($this->ids, 'p.7'))
                ->price(130)
                ->prices('rule-a', 150)
                ->prices('rule-a', 140, 'default', null, 3)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.7.1'))
                        ->prices('rule-a', 160)
                        ->prices('rule-a', 150, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.7.2'))
                        ->build()
                )
                ->build(),

            // no rule = 140€  ||  rule-a = 170€
            (new ProductBuilder($this->ids, 'p.8'))
                ->price(140)
                ->prices('rule-a', 160)
                ->prices('rule-a', 150, 'default', null, 3)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.8.1'))
                        ->prices('rule-a', 170)
                        ->prices('rule-a', 160, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.8.2'))
                        ->prices('rule-a', 180)
                        ->prices('rule-a', 170, 'default', null, 3)
                        ->build()
                )
                ->build(),

            // no-rule = 150€   ||   rule-a  = 160€
            (new ProductBuilder($this->ids, 'p.9'))
                ->price(150)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.9.1'))
                        ->prices('rule-a', 170)
                        ->prices('rule-a', 160, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.9.2'))
                        ->price(160)
                        ->build()
                )
                ->build(),

            // no rule = 150€  ||  rule-a = 150€
            (new ProductBuilder($this->ids, 'p.10'))
                ->price(160)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.10.1'))
                        ->prices('rule-a', 170)
                        ->prices('rule-a', 160, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.10.2'))
                        ->price(150)
                        ->build()
                )
                ->build(),

            // no-rule = 170  || rule-a = 190  || rule-b = 200
            (new ProductBuilder($this->ids, 'p.11'))
                ->price(170)
                ->prices('rule-a', 190)
                ->prices('rule-a', 180, 'default', null, 3)
                ->prices('rule-b', 200)
                ->prices('rule-b', 190, 'default', null, 3)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.11.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.11.2'))
                        ->build()
                )
                ->build(),

            // no rule = 180 ||  rule-a = 210  || rule-b = 180 || a+b = 210 || b+a = 200/180
            (new ProductBuilder($this->ids, 'p.12'))
                ->price(180)
                ->visibility(Defaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.12.1'))
                        ->prices('rule-a', 220)
                        ->prices('rule-a', 210, 'default', null, 3)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.12.2'))
                        ->prices('rule-a', 210)
                        ->prices('rule-a', 200, 'default', null, 3)
                        ->prices('rule-b', 200)
                        ->prices('rule-b', 190, 'default', null, 3)
                        ->build()
                )
                ->build(),

            // no rule = 190 ||  rule-a = 220  || rule-b = 190 || a+b = 220 || b+a = 210/190
            (new ProductBuilder($this->ids, 'p.13'))
                ->price(190)
                ->visibility(Defaults::SALES_CHANNEL)
                ->prices('rule-a', 230)
                ->prices('rule-a', 220, 'default', null, 3)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.13.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.13.2'))
                        ->prices('rule-a', 220)
                        ->prices('rule-a', 210, 'default', null, 3)
                        ->prices('rule-b', 210)
                        ->prices('rule-b', 200, 'default', null, 3)
                        ->build()
                )
                ->build(),

            (new ProductBuilder($this->ids, 'dal-1'))
                ->name('Default')
                ->category('navi')
                ->customField('testField', 'Silk')
                ->visibility(Defaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m1')
                ->price(50)
                ->releaseDate('2019-01-01 10:11:00')
                ->purchasePrice(0)
                ->stock(2)
                ->category('c1')
                ->category('c2')
                ->property('red', 'color')
                ->property('xl', 'size')
                ->add('weight', 12.3)
                ->add('height', 9.3)
                ->add('width', 1.3)
                ->translation($secondLanguage, 'name', 'Second')
                ->translation($thirdLanguage, 'name', 'Third')
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());
    }

    private function createLanguage(?string $parentId = null): string
    {
        $id = Uuid::randomHex();

        $languageRepository = $this->getContainer()->get('language.repository');

        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'parentId' => $parentId,
                    'translationCode' => [
                        'code' => Uuid::randomHex(),
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'salesChannels' => [
                        ['id' => Defaults::SALES_CHANNEL],
                    ],
                    'salesChannelDefaultAssignments' => [
                        ['id' => Defaults::SALES_CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        return $id;
    }

    private function createIndexingContext(): Context
    {
        $context = Context::createDefaultContext();
        $context->addExtension('currencies', $this->getContainer()->get('currency.repository')->search(new Criteria(), Context::createDefaultContext()));

        return $context;
    }

    private function fetchProductFromDAL(IdsCollection $ids, Context $languageContext): ProductEntity
    {
        return $this->getContainer()->get('product.repository')->search(new Criteria([$ids->get('dal-1')]), $languageContext)->first();
    }
}
