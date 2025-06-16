<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\BucketResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\RangeResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedProductDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ProductExtension;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util\DateHistogramCase;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\SalesChannelLanguageLoader;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Shopware\Tests\Integration\Elasticsearch\Product\Fixture\ProductsFixture;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ElasticsearchProductTest extends TestCase
{
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;

    private Client $client;

    private ProductDefinition $productDefinition;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    private ElasticsearchHelper $helper;

    private IdsCollection $ids;

    private Connection $connection;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private string $navigationId;

    private string $currencyId = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    private string $anotherCurrencyId = '2c962ddb7b3346f29c748a9d3b884302';

    private AbstractElasticsearchDefinition $definition;

    private ElasticsearchIndexingUtils $utils;

    private Context $context;

    protected function setUp(): void
    {
        $this->definition = static::getContainer()->get(ElasticsearchProductDefinition::class);
        $this->utils = static::getContainer()->get(ElasticsearchIndexingUtils::class);

        $this->helper = static::getContainer()->get(ElasticsearchHelper::class);
        $this->client = static::getContainer()->get(Client::class);
        $this->productDefinition = static::getContainer()->get(ProductDefinition::class);
        $this->languageRepository = static::getContainer()->get('language.repository');

        static::getContainer()->get(SalesChannelLanguageLoader::class)->reset();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->navigationId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)]
        );

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);

        $this->productRepository = static::getContainer()->get('product.repository');

        $this->ids = new IdsCollection();
        $this->ids->set('navi', $this->navigationId);

        $this->context = Context::createDefaultContext();

        parent::setUp();
    }

    #[BeforeClass]
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->executeStatement('
            DROP TABLE IF EXISTS `extended_product`;
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`product_id`, `product_version_id`) REFERENCES `product` (`id`, `version_id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            )
        ');

        $connection->beginTransaction();
    }

    #[AfterClass]
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
        $connection->executeStatement('DROP TABLE `extended_product`');
    }

    public function testIndexing(): IdsCollection
    {
        try {
            $this->connection->executeStatement('DELETE FROM product');

            $this->clearElasticsearch();

            $this->resetStopWords();

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
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                ],
                [
                    'id' => $this->anotherCurrencyId,
                    'name' => 'test',
                    'factor' => 0.001,
                    'symbol' => 'B',
                    'decimalPrecision' => 2,
                    'shortName' => 'B',
                    'isoCode' => 'B',
                    'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                    'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.05, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                ],
            ];

            static::getContainer()
                ->get('currency.repository')
                ->upsert($currencies, $this->context);

            $this->createData();

            $this->indexElasticSearch();

            $criteria = new Criteria();
            $criteria->addFilter(
                new NandFilter([new EqualsFilter('salesChannelDomains.id', null)])
            );

            $index = $this->helper->getIndexName($this->productDefinition);

            $exists = $this->client->indices()->exists(['index' => $index]);
            static::assertTrue($exists, 'Expected elasticsearch indices present');

            return $this->ids;
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testUpdate(IdsCollection $ids): void
    {
        try {
            $this->ids = $ids;
            $context = $this->context;

            $this->productRepository->upsert([
                (new ProductBuilder($this->ids, 'u7', 300))
                    ->price(100)
                    ->build(),
            ], $context);

            $this->refreshIndex();

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsFilter('productNumber', 'u7'));

            // products should be updated immediately
            $searcher = $this->createEntitySearcher();
            $result = $searcher->search($this->productDefinition, $criteria, $context);
            static::assertCount(1, $result->getIds());

            $this->productRepository->delete([['id' => $ids->get('u7')]], $context);

            $this->refreshIndex();
            $result = $searcher->search($this->productDefinition, $criteria, $context);
            static::assertCount(0, $result->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testEmptySearch(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(\count($data->prefixed('product-')), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testPagination(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            // check pagination
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->setLimit(1);
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(\count($data->prefixed('product-')), $products->getTotal());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testEqualsFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsFilter('stock', 2));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testEqualsFilterWithNumericEncodedBoolFields(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsFilter('active', 1));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(9, $products->getIds());
            static::assertSame(9, $products->getTotal());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testRangeFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple range filter
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new RangeFilter('product.stock', [RangeFilter::GTE => 10]));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(6, $products->getIds());
            static::assertSame(6, $products->getTotal());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testEqualsAnyFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check filter for categories
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsAnyFilter('product.categoriesRo.id', [$data->get('c1')]));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(3, $products->getIds());
            static::assertSame(3, $products->getTotal());
            static::assertContains($data->get('product-1'), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testMultiNotFilterFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check filter for categories
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [
                        new RangeFilter('product.cheapestPrice', [RangeFilter::LTE => 101]),
                        new ContainsFilter('product.name', 'ilk'),
                    ]
                )
            );

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertCount(6, $products->getIds());
            static::assertSame(6, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());
            static::assertContains($data->get('product-3'), $products->getIds());
            static::assertContains($data->get('product-4'), $products->getIds());
            static::assertContains($data->get('product-5'), $products->getIds());
            static::assertContains($data->get('product-6'), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    /**
     * @param array<string> $expectedProducts
     * @param Filter $filter
     */
    #[Depends('testIndexing')]
    #[DataProvider('multiFilterWithOneToManyRelationProvider')]
    public function testMultiFilterWithOneToManyRelation($filter, $expectedProducts, IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            $criteria = new Criteria($data->prefixed('s-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter($filter);
            $products = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertCount(\count($expectedProducts), $products->getIds());
            static::assertSame(\array_map(fn ($item) => $data->get($item), $expectedProducts), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    /**
     * @return array<int, array<MultiFilter|string[]>>
     */
    public static function multiFilterWithOneToManyRelationProvider(): array
    {
        return require __DIR__ . '/Fixture/MultiFilterWithOneToManyRelation.php';
    }

    #[Depends('testIndexing')]
    public function testContainsFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new ContainsFilter('product.name', 'tilk'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-3'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new ContainsFilter('product.name', 'subber'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(0, $products->getIds());
            static::assertSame(0, $products->getTotal());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new ContainsFilter('product.name', 'Rubb'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new ContainsFilter('product.name', 'bber'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testPrefixFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            // Foo Stilk is ignored because it is not a prefix
            $criteria->addFilter(new PrefixFilter('product.name', 'Sti'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-3'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new PrefixFilter('product.name', 'subber'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(0, $products->getIds());
            static::assertSame(0, $products->getTotal());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new PrefixFilter('product.name', 'Rubb'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new PrefixFilter('product.name', 'Spacht'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-6'), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSuffixFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new SuffixFilter('product.name', 'tilk'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-3'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new SuffixFilter('product.name', 'subber'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(0, $products->getIds());
            static::assertSame(0, $products->getTotal());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new SuffixFilter('product.name', 'bber'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-2'), $products->getIds());

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new SuffixFilter('product.name', 'company'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertCount(1, $products->getIds());
            static::assertSame(1, $products->getTotal());
            static::assertContains($data->get('product-6'), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSingleGroupBy(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addGroupField(new FieldGrouping('stock'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);

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
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testMultiGroupBy(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addGroupField(new FieldGrouping('stock'));
            $criteria->addGroupField(new FieldGrouping('childCount'));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertCount(5, $products->getIds());
            static::assertContains($data->get('product-1'), $products->getIds());
            static::assertContains($data->get('product-2'), $products->getIds());
            static::assertContains($data->get('product-3'), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testAvgAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new AvgAggregation('avg-stock', 'product.stock'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('avg-stock'));

            $result = $aggregations->get('avg-stock');
            static::assertInstanceOf(AvgResult::class, $result);

            static::assertTrue(FloatComparator::equals(194.57142857143, $result->getAvg()));
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testTermsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new TermsAggregation('manufacturer-ids', 'product.manufacturerId'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testTermsAggregationWithAvg(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(
                new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new AvgAggregation('avg-stock', 'product.stock'))
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());

            $price = $bucket->getResult();
            static::assertInstanceOf(AvgResult::class, $price);
            static::assertSame(2.0, $price->getAvg());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(AvgResult::class, $price);
            static::assertTrue(FloatComparator::equals(136.66666666667, $price->getAvg()));

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());

            $price = $bucket->getResult();
            static::assertInstanceOf(AvgResult::class, $price);
            static::assertSame(300.0, $price->getAvg());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testTermsAggregationWithAssociation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new TermsAggregation('manufacturer-ids', 'product.manufacturerId'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSumAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new SumAggregation('sum-stock', 'product.stock'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('sum-stock'));

            $result = $aggregations->get('sum-stock');
            static::assertInstanceOf(SumResult::class, $result);

            static::assertSame(1362.0, $result->getSum());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSumAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(
                new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new SumAggregation('price-sum', 'product.price'))
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(SumResult::class, $price);
            static::assertSame(0.0, $price->getSum());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(SumResult::class, $price);
            static::assertSame(0.0, $price->getSum());

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(SumResult::class, $price);
            static::assertSame(0.0, $price->getSum());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testMaxAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new MaxAggregation('max-stock', 'product.stock'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('max-stock'));

            $result = $aggregations->get('max-stock');
            static::assertInstanceOf(MaxResult::class, $result);

            static::assertSame(350.0, $result->getMax());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testMaxAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(
                new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new MaxAggregation('stock-max', 'product.stock'))
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(MaxResult::class, $price);
            static::assertSame(2.0, $price->getMax());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(MaxResult::class, $price);
            static::assertSame(300.0, $price->getMax());

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(MaxResult::class, $price);
            static::assertSame(300.0, $price->getMax());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testMinAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new MinAggregation('min-stock', 'product.stock'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('min-stock'));

            $result = $aggregations->get('min-stock');
            static::assertInstanceOf(MinResult::class, $result);

            static::assertSame(1.0, $result->getMin());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testMinAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(
                new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new MinAggregation('stock-min', 'product.stock'))
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());
            $stock = $bucket->getResult();
            static::assertInstanceOf(MinResult::class, $stock);
            static::assertSame(2.0, $stock->getMin());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());
            $stock = $bucket->getResult();
            static::assertInstanceOf(MinResult::class, $stock);
            static::assertSame(10.0, $stock->getMin());

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());
            $stock = $bucket->getResult();
            static::assertInstanceOf(MinResult::class, $stock);
            static::assertSame(300.0, $stock->getMin());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCountAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new CountAggregation('manufacturer-count', 'product.manufacturerId'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-count'));

            $result = $aggregations->get('manufacturer-count');
            static::assertInstanceOf(CountResult::class, $result);

            static::assertSame(6, $result->getCount());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCountAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(
                new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new CountAggregation('stock-count', 'product.stock'))
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());
            $stock = $bucket->getResult();
            static::assertInstanceOf(CountResult::class, $stock);
            static::assertSame(1, $stock->getCount());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());
            $stock = $bucket->getResult();
            static::assertInstanceOf(CountResult::class, $stock);
            static::assertSame(3, $stock->getCount());

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());
            $stock = $bucket->getResult();
            static::assertInstanceOf(CountResult::class, $stock);
            static::assertSame(2, $stock->getCount());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testStatsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new StatsAggregation('price-stats', 'product.cheapestPrice'));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('price-stats'));

            $result = $aggregations->get('price-stats');
            static::assertInstanceOf(StatsResult::class, $result);

            static::assertSame(50.0, $result->getMin());
            static::assertSame(300.0, $result->getMax());
            static::assertIsFloat($result->getAvg());
            static::assertTrue(FloatComparator::equals(192.85714285714, $result->getAvg()));
            static::assertSame(1350.0, $result->getSum());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testStatsAggregationWithTermsAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(
                new TermsAggregation('manufacturer-ids', 'product.manufacturerId', null, null, new StatsAggregation('price-stats', 'product.cheapestPrice'))
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturer-ids'));

            $result = $aggregations->get('manufacturer-ids');
            static::assertInstanceOf(TermsResult::class, $result);

            static::assertCount(3, $result->getBuckets());

            static::assertContains($data->get('m1'), $result->getKeys());
            static::assertContains($data->get('m2'), $result->getKeys());
            static::assertContains($data->get('m3'), $result->getKeys());

            $bucket = $result->get($data->get('m1'));
            static::assertNotNull($bucket);
            static::assertSame(1, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(StatsResult::class, $price);
            static::assertSame(50.0, $price->getSum());
            static::assertSame(50.0, $price->getMax());
            static::assertSame(50.0, $price->getMin());
            static::assertSame(50.0, $price->getAvg());

            $bucket = $result->get($data->get('m2'));
            static::assertNotNull($bucket);
            static::assertSame(3, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(StatsResult::class, $price);
            static::assertSame(450.0, $price->getSum());
            static::assertSame(200.0, $price->getMax());
            static::assertSame(100.0, $price->getMin());
            static::assertSame(150.0, $price->getAvg());

            $bucket = $result->get($data->get('m3'));
            static::assertNotNull($bucket);
            static::assertSame(2, $bucket->getCount());
            $price = $bucket->getResult();
            static::assertInstanceOf(StatsResult::class, $price);
            static::assertSame(550.0, $price->getSum());
            static::assertSame(300.0, $price->getMax());
            static::assertSame(250.0, $price->getMin());
            static::assertSame(275.0, $price->getAvg());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testEntityAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new EntityAggregation('manufacturers', 'product.manufacturerId', ProductManufacturerDefinition::ENTITY_NAME));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturers'));

            $result = $aggregations->get('manufacturers');
            static::assertInstanceOf(EntityResult::class, $result);

            static::assertCount(3, $result->getEntities());

            static::assertTrue($result->getEntities()->has($data->get('m1')));
            static::assertTrue($result->getEntities()->has($data->get('m2')));
            static::assertTrue($result->getEntities()->has($data->get('m3')));
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testEntityAggregationWithTermQuery(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = (new Criteria($data->prefixed('p')))->setTerm('Grouped');
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new EntityAggregation('manufacturers', 'product.manufacturerId', ProductManufacturerDefinition::ENTITY_NAME));

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('manufacturers'));

            $result = $aggregations->get('manufacturers');
            static::assertInstanceOf(EntityResult::class, $result);

            static::assertCount(2, $result->getEntities());

            static::assertTrue($result->getEntities()->has($data->get('m2')));
            static::assertTrue($result->getEntities()->has($data->get('m3')));
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testTermAlgorithm(IdsCollection $data): void
    {
        try {
            $terms = ['Spachtelmasse', 'Spachtel', 'Masse', 'Achtel', 'Some', 'some spachtel', 'Some Achtel', 'Sachtel'];

            $searcher = $this->createEntitySearcher();

            foreach ($terms as $term) {
                $criteria = new Criteria();
                $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
                $criteria->setTerm($term);

                $products = $searcher->search($this->productDefinition, $criteria, $this->context);

                static::assertSame(1, $products->getTotal(), \sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));

                $term = strtolower($term);
                $products = $searcher->search($this->productDefinition, $criteria, $this->context);
                static::assertSame(1, $products->getTotal(), \sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));

                $term = strtoupper($term);
                $products = $searcher->search($this->productDefinition, $criteria, $this->context);
                static::assertSame(1, $products->getTotal(), \sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));
            }
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterAggregation(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(
                new FilterAggregation(
                    'filter',
                    new AvgAggregation('avg-stock', 'product.stock'),
                    [new EqualsAnyFilter('product.id', $data->getList(['product-1', 'product-2']))]
                )
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertCount(1, $aggregations);

            static::assertTrue($aggregations->has('avg-stock'));

            $result = $aggregations->get('avg-stock');
            static::assertInstanceOf(AvgResult::class, $result);

            static::assertSame(6.0, $result->getAvg());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterAggregationWithNestedFilterAndAggregation(IdsCollection $data): void
    {
        $aggregator = $this->createEntityAggregator();

        try {
            // Assert that property is not contained in aggregation if we filter for different property
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            // product 1 (m1): red   + xl             product 2 (m2): green + l
            $criteria->addFilter(
                new EqualsAnyFilter('id', $data->getList(['product-1', 'product-2']))
            );
            $criteria->addState('debug');

            $criteria->addAggregation(
                new FilterAggregation(
                    'properties-filtered',
                    new TermsAggregation('properties', 'product.properties.id'),
                    [
                        new EqualsAnyFilter('product.properties.id', [$data->get('red')]),
                    ]
                )
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);
            $result = $aggregations->get('properties');
            static::assertInstanceOf(TermsResult::class, $result);
            static::assertNotContains($data->get('green'), $result->getKeys());
            static::assertNotContains($data->get('xl'), $result->getKeys());
            static::assertNotContains($data->get('l'), $result->getKeys());
            static::assertContains($data->get('red'), $result->getKeys());

            // Test that property is contained in aggregation if we filter for groups
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            // product 1 (m1): red   + xl             product 2 (m2): green + l
            $criteria->addFilter(
                new EqualsAnyFilter('id', $data->getList(['product-1', 'product-2']))
            );

            $criteria->addState('debug');
            $criteria->addAggregation(
                new FilterAggregation(
                    'properties-filter',
                    new TermsAggregation('properties', 'product.properties.id'),
                    [
                        new EqualsAnyFilter('properties.groupId', [$data->get('color')]),
                        new EqualsAnyFilter('manufacturerId', $data->getList(['m1', 'm2'])),
                        new EqualsAnyFilter('manufacturer.id', $data->getList(['m1', 'm2'])),
                    ]
                )
            );
            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);
            $result = $aggregations->get('properties');

            static::assertInstanceOf(TermsResult::class, $result);
            static::assertContains($data->get('red'), $result->getKeys());
            static::assertContains($data->get('green'), $result->getKeys());
            static::assertNotContains($data->get('xl'), $result->getKeys());
            static::assertNotContains($data->get('l'), $result->getKeys());
            static::assertCount(2, $result->getKeys());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterForProperties(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check filter for categories
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsAnyFilter('product.properties.id', [$data->get('red')]));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertCount(2, $products->getIds());
            static::assertTrue($products->has($data->get('product-1')));
            static::assertTrue($products->has($data->get('product-3')));
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testNestedFilterAggregationWithRootQuery(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // Assert that property is contained in aggregation if we filter for manufacturer
            // Test that property is contained in aggregation if we filter for groups
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            // product 1 (m1): red   + xl             product 2 (m2): green + l
            $criteria->addFilter(
                new EqualsAnyFilter('id', $data->getList(['product-1', 'product-2']))
            );

            $criteria->addAggregation(
                new FilterAggregation(
                    'properties-filtered',
                    new TermsAggregation('properties', 'product.properties.id'),
                    [new EqualsAnyFilter('product.manufacturerId', [$data->get('m1')])]
                )
            );

            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            $result = $aggregations->get('properties');
            static::assertInstanceOf(BucketResult::class, $result);
            static::assertContains($data->get('xl'), $result->getKeys());
            static::assertContains($data->get('red'), $result->getKeys());

            static::assertNotContains($data->get('l'), $result->getKeys());
            static::assertNotContains($data->get('green'), $result->getKeys());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterAggregationWithRootFilter(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // Assert that property is not contained in aggregation if we filter for manufacturer
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            // product 1 (m1): red   + xl             product 2 (m2): green + l
            $criteria->addFilter(
                new EqualsAnyFilter('id', $data->getList(['product-1', 'product-2']))
            );

            $criteria->addAggregation(
                new FilterAggregation(
                    'properties-filtered',
                    new TermsAggregation('properties', 'product.properties.id'),
                    [new EqualsAnyFilter('product.manufacturerId', [$data->get('m2')])]
                )
            );
            $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            $result = $aggregations->get('properties');
            static::assertInstanceOf(BucketResult::class, $result);
            static::assertNotContains($data->get('xl'), $result->getKeys());
            static::assertNotContains($data->get('red'), $result->getKeys());

            static::assertContains($data->get('l'), $result->getKeys());
            static::assertContains($data->get('green'), $result->getKeys());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    #[DataProvider('dateHistogramProvider')]
    public function testDateHistogram(DateHistogramCase $case, IdsCollection $data): void
    {
        try {
            $context = $this->context;

            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

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

            $histogram = $result->get('release-histogram');
            static::assertInstanceOf(DateHistogramResult::class, $histogram);

            static::assertCount(\count($case->getBuckets()), $histogram->getBuckets(), print_r($histogram->getBuckets(), true));

            foreach ($case->getBuckets() as $key => $count) {
                static::assertTrue($histogram->has($key));
                $bucket = $histogram->get($key);
                static::assertNotNull($bucket);
                static::assertSame($count, $bucket->getCount());
            }
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    /**
     * @return array<int, array<int, DateHistogramCase>>
     */
    public static function dateHistogramProvider(): array
    {
        return require __DIR__ . '/Fixture/DateHistogram.php';
    }

    #[Depends('testIndexing')]
    public function testDateHistogramWithNestedAvg(IdsCollection $data): void
    {
        try {
            $aggregator = $this->createEntityAggregator();

            // check simple search without any restrictions
            $criteria = new Criteria($data->prefixed('p'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $criteria->addAggregation(
                new DateHistogramAggregation(
                    'release-histogram',
                    'product.releaseDate',
                    DateHistogramAggregation::PER_MONTH,
                    null,
                    new AvgAggregation('price', 'product.stock')
                )
            );

            $result = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

            static::assertTrue($result->has('release-histogram'));

            $histogram = $result->get('release-histogram');
            static::assertInstanceOf(DateHistogramResult::class, $histogram);

            static::assertCount(5, $histogram->getBuckets());

            $bucket = $histogram->get('2019-01-01 00:00:00');
            static::assertInstanceOf(Bucket::class, $bucket);
            $price = $bucket->getResult();
            static::assertInstanceOf(AvgResult::class, $price);
            static::assertSame(6.0, $price->getAvg());

            $bucket = $histogram->get('2019-06-01 00:00:00');
            static::assertInstanceOf(Bucket::class, $bucket);
            $price = $bucket->getResult();
            static::assertInstanceOf(AvgResult::class, $price);
            static::assertSame(100.0, $price->getAvg());

            $bucket = $histogram->get('2020-09-01 00:00:00');
            static::assertInstanceOf(Bucket::class, $bucket);
            $price = $bucket->getResult();
            static::assertInstanceOf(AvgResult::class, $price);
            static::assertSame(300.0, $price->getAvg());

            $bucket = $histogram->get('2021-12-01 00:00:00');
            static::assertInstanceOf(Bucket::class, $bucket);
            $price = $bucket->getResult();
            static::assertInstanceOf(AvgResult::class, $price);
            static::assertSame(300.0, $price->getAvg());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterCustomTextField(IdsCollection $data): void
    {
        try {
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsFilter('customFields.testField', 'silk'));

            $result = $this->createEntitySearcher()->search($this->productDefinition, $criteria, Context::createDefaultContext());

            static::assertSame(1, $result->getTotal());
            static::assertTrue($result->has($data->get('product-1')));
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterCustomTextFieldEqualNull(IdsCollection $data): void
    {
        try {
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsFilter('customFields.testField', null));

            $result = $this->createEntitySearcher()->search($this->productDefinition, $criteria, Context::createDefaultContext());

            static::assertSame(1, $result->getTotal());
            static::assertTrue($result->has($data->get('product-7')));
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testXorQuery(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $multiFilter = new MultiFilter(
                MultiFilter::CONNECTION_XOR,
                [
                    new EqualsFilter('taxId', $data->get('t1')),
                    new EqualsFilter('manufacturerId', $data->get('m2')),
                ]
            );
            $criteria->addFilter($multiFilter);

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertSame(3, $products->getTotal());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testNegativXorQuery(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $multiFilter = new MultiFilter(
                MultiFilter::CONNECTION_XOR,
                [
                    new EqualsFilter('taxId', 'foo'),
                    new EqualsFilter('manufacturerId', 'baa'),
                ]
            );
            $criteria->addFilter($multiFilter);

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);
            static::assertSame(0, $products->getTotal());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testTotalWithGroupFieldAndPostFilter(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();
            // check simple equals filter
            $criteria = new Criteria($data->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addGroupField(new FieldGrouping('stock'));
            $criteria->addPostFilter(new EqualsFilter('manufacturerId', $data->get('m2')));

            $products = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertSame(3, $products->getTotal());
            static::assertCount(3, $products->getIds());
            static::assertContains($data->get('product-2'), $products->getIds());
            static::assertContains($data->get('product-3'), $products->getIds());
            static::assertContains($data->get('product-4'), $products->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
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
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $criteria->addFilter(new RangeFilter('stock', [
                RangeFilter::GTE => 0,
            ]));

            $ids = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertSame($expected, $ids->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
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
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addSorting(new FieldSorting('name'));

            $ids = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertSame($expected, $ids->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testMaxLimit(IdsCollection $data): void
    {
        try {
            $searcher = $this->createEntitySearcher();

            // check simple equals filter
            $criteria = new Criteria($data->getList(['product-1', 'product-2', 'product-3', 'product-4', 'product-5', 'product-6', 'n7', 'n8', 'n9', 'n10', 'n11']));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $ids = $searcher->search($this->productDefinition, $criteria, $this->context);

            static::assertCount(11, $ids->getIds());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testStorefrontListing(): void
    {
        try {
            $this->helper->setEnabled(true);

            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(
                    Uuid::randomHex(),
                    TestDefaults::SALES_CHANNEL,
                    [
                        SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    ]
                );

            $request = new Request();

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $result = static::getContainer()->get(ProductListingRoute::class)
                ->load($context->getSalesChannel()->getNavigationCategoryId(), $request, $context, $criteria);

            $listing = $result->getResult();

            // ensure that all data loaded by elastic search
            static::assertTrue($listing->hasState(ElasticsearchEntitySearcher::RESULT_STATE));
            static::assertTrue($listing->getAggregations()->hasState(ElasticsearchEntityAggregator::RESULT_STATE));

            static::assertTrue($listing->getTotal() > 0);
            static::assertTrue($listing->getAggregations()->has('shipping-free'));
            static::assertTrue($listing->getAggregations()->has('rating'));
            static::assertTrue($listing->getAggregations()->has('price'));
            static::assertTrue($listing->getAggregations()->has('properties'));
            static::assertTrue($listing->getAggregations()->has('manufacturer'));
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSortingIsCaseInsensitive(IdsCollection $data): void
    {
        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $criteria->addFilter(new EqualsFilter('categoriesRo.id', $data->get('cs1')));
            $criteria->addSorting(new FieldSorting('name'));

            $searcher = $this->createEntitySearcher();
            $ids = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();

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
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCheapestPriceFilter(IdsCollection $ids): void
    {
        try {
            $cases = $this->providerCheapestPriceFilter();

            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(
                    Uuid::randomHex(),
                    TestDefaults::SALES_CHANNEL,
                    [
                        SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    ]
                );

            $searcher = $this->createEntitySearcher();

            foreach ($cases as $message => $case) {
                $affected = [...$ids->prefixed('p.'), ...$ids->prefixed('v.')];
                $criteria = new Criteria(array_values($affected));
                $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

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
                    static::assertTrue($result->has($ids->get($key)), \sprintf('Missing id %s in case `%s`', $key, $message));
                }
            }
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    /**
     * @return array<string, array{from: int, to: int, expected: string[], rules?: string[]}>
     */
    public function providerCheapestPriceFilter(): iterable
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
        yield 'Test 210€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 210, 'to' => 211, 'expected' => ['v.12.1']];
        yield 'Test 220€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 220, 'to' => 221, 'expected' => ['v.13.1']];
        yield 'Test 190€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 190, 'to' => 191, 'expected' => ['v.11.1', 'v.11.2', 'v.12.2']];
    }

    #[Depends('testIndexing')]
    public function testCheapestPriceSorting(IdsCollection $ids): void
    {
        try {
            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(
                    Uuid::randomHex(),
                    TestDefaults::SALES_CHANNEL,
                    [
                        SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    ]
                );

            foreach ($this->cheapestPriceSortingProvider() as $message => $case) {
                $context->setRuleIds($ids->getList($case['rules']));

                $this->assertSorting($message, $ids, $context, $case, FieldSorting::ASCENDING);

                $this->assertSorting($message, $ids, $context, $case, FieldSorting::DESCENDING);
            }
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{ids: string[], rules: string[]}>
     */
    public function cheapestPriceSortingProvider(): iterable
    {
        yield from require __DIR__ . '/Fixture/CheapestPriceSorting.php';
    }

    #[Depends('testIndexing')]
    public function testCheapestPriceAggregation(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $affected = [...$ids->prefixed('p.'), ...$ids->prefixed('v.')];
            $criteria = new Criteria(array_values($affected));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
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
                static::assertSame($case['min'], $aggregation->getMin(), \sprintf('Case `%s` failed', $message));
                static::assertSame($case['max'], $aggregation->getMax(), \sprintf('Case `%s` failed', $message));
            }
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCheapestPricePercentageFilterAndSorting(IdsCollection $ids): void
    {
        try {
            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(
                    Uuid::randomHex(),
                    TestDefaults::SALES_CHANNEL,
                    [
                        SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    ]
                );

            $searcher = $this->createEntitySearcher();

            $cases = $this->providerCheapestPricePercentageFilterAndSorting();

            /**
             * @var string $message
             */
            foreach ($cases as $message => $case) {
                $criteria = new Criteria($ids->prefixed('product-'));
                $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

                if ($case['operator']) {
                    $operator = (string) $case['operator'];
                    $percentage = (int) $case['percentage'];

                    $criteria->addFilter(
                        new RangeFilter('product.cheapestPrice.percentage', [
                            $operator => $percentage,
                        ])
                    );
                }

                $criteria->addSorting(new FieldSorting('product.cheapestPrice.percentage', $case['direction']));
                $criteria->addSorting(new FieldSorting('product.productNumber', $case['direction']));

                $result = $searcher->search($this->productDefinition, $criteria, $context->getContext());

                static::assertCount(is_countable($case['ids']) ? \count($case['ids']) : 0, $result->getIds(), \sprintf('Case `%s` failed', $message));
                static::assertSame(array_map(fn (string $id) => $ids->get($id), $case['ids']), $result->getIds(), \sprintf('Case `%s` failed', $message));
            }
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    /**
     * @return \Generator<array{ids: array<string>, operator: RangeFilter::*|null, percentage: int|null, direction: FieldSorting::*}>
     */
    public function providerCheapestPricePercentageFilterAndSorting(): \Generator
    {
        yield 'Test filter with greater than 50 percent price to list ratio sorted descending' => [
            'ids' => ['product-1', 'product-4'],
            'operator' => RangeFilter::GT,
            'percentage' => 50,
            'direction' => FieldSorting::DESCENDING,
        ];

        yield 'Test filter with greater than 50 percent price to list ratio sorted ascending' => [
            'ids' => ['product-4', 'product-1'],
            'operator' => RangeFilter::GT,
            'percentage' => 50,
            'direction' => FieldSorting::ASCENDING,
        ];

        yield 'Test filter with less than 50 percent price to list ratio sorted descending' => [
            'ids' => ['product-2', 'product-5', 'product-3'],
            'operator' => RangeFilter::LT,
            'percentage' => 50,
            'direction' => FieldSorting::DESCENDING,
        ];

        yield 'Test filter with less than 50 percent price to list ratio sorted ascending' => [
            'ids' => ['product-3', 'product-5', 'product-2'],
            'operator' => RangeFilter::LT,
            'percentage' => 50,
            'direction' => FieldSorting::ASCENDING,
        ];

        yield 'Test percent price to list ratio sorted descending' => [
            'ids' => ['product-1', 'product-4', 'product-2', 'product-5', 'product-7', 'product-6', 'product-3'],
            'operator' => null,
            'percentage' => null,
            'direction' => FieldSorting::DESCENDING,
        ];

        yield 'Test percent price to list ratio sorted ascending' => [
            'ids' => ['product-3', 'product-6', 'product-7', 'product-5', 'product-2', 'product-4', 'product-1'],
            'operator' => null,
            'percentage' => null,
            'direction' => FieldSorting::ASCENDING,
        ];
    }

    #[Depends('testIndexing')]
    public function testNestedSorting(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('sort.'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('tags.name'));

        $searcher = $this->createEntitySearcher();
        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        static::assertSame($ids->get('sort.bisasam'), $result->getIds()[0]);
        static::assertSame($ids->get('sort.glumanda'), $result->getIds()[1]);
        static::assertSame($ids->get('sort.pikachu'), $result->getIds()[2]);

        $criteria = new Criteria($ids->prefixed('sort.'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('tags.name', FieldSorting::DESCENDING));
        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        static::assertSame($ids->get('sort.pikachu'), $result->getIds()[0]);
        static::assertSame($ids->get('sort.glumanda'), $result->getIds()[1]);
        static::assertSame($ids->get('sort.bisasam'), $result->getIds()[2]);
    }

    #[Depends('testIndexing')]
    public function testCheapestPricePercentageAggregation(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria($ids->prefixed('product-'));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

            $criteria->addAggregation(new StatsAggregation('percentage', 'product.cheapestPrice.percentage'));

            $aggregator = $this->createEntityAggregator();

            $result = $aggregator->aggregate($this->productDefinition, $criteria, $context);

            $aggregation = $result->get('percentage');

            static::assertInstanceOf(StatsResult::class, $aggregation);
            static::assertSame(0.0, $aggregation->getMin());
            static::assertSame(66.67, $aggregation->getMax());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testLanguageFieldsWorkSimilarToDAL(IdsCollection $ids): void
    {
        $context = $this->createIndexingContext();

        $dal1 = $ids->getBytes('dal-1');

        // Fetch: Default language
        $esProducts = $this->definition->fetch([$dal1], $context);

        $esProduct = $esProducts[$ids->get('dal-1')];

        $criteria = new Criteria([$ids->get('dal-1')]);
        $dalProduct = $this->productRepository->search($criteria, $context)->first();

        static::assertInstanceOf(ProductEntity::class, $dalProduct);
        static::assertSame((string) $dalProduct->getTranslation('name'), (string) $esProduct['name'][Defaults::LANGUAGE_SYSTEM]);
        static::assertSame((string) $dalProduct->getTranslation('description'), (string) $esProduct['description'][Defaults::LANGUAGE_SYSTEM]);
        static::assertSame($dalProduct->getTranslation('customFields'), $esProduct['customFields'][Defaults::LANGUAGE_SYSTEM]);

        // Fetch: Second language
        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$ids->get('language-1'), Defaults::LANGUAGE_SYSTEM]);
        $languageContext->addExtensions($context->getExtensions());
        $esProducts = $this->definition->fetch([$dal1], $languageContext);

        $esProduct = $esProducts[$ids->get('dal-1')];

        $criteria = new Criteria([$ids->get('dal-1')]);
        $dalProduct = $this->productRepository->search($criteria, $languageContext)->first();

        static::assertInstanceOf(ProductEntity::class, $dalProduct);
        static::assertSame((string) $dalProduct->getTranslation('name'), (string) $esProduct['name'][$ids->get('language-1')]);
        static::assertSame((string) $dalProduct->getTranslation('description'), (string) $esProduct['description'][$ids->get('language-1')]);
        static::assertSame($dalProduct->getTranslation('customFields'), $esProduct['customFields'][Defaults::LANGUAGE_SYSTEM]);

        // Fetch: Third language
        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$ids->get('language-2'), $ids->get('language-1'), Defaults::LANGUAGE_SYSTEM]);
        $languageContext->addExtensions($context->getExtensions());
        $esProducts = $this->definition->fetch([$dal1], $languageContext);

        $esProduct = $esProducts[$ids->get('dal-1')];

        $criteria = new Criteria([$ids->get('dal-1')]);
        $dalProduct = $this->productRepository->search($criteria, $languageContext)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $dalProduct);
        static::assertSame((string) $dalProduct->getTranslation('name'), (string) $esProduct['name'][$ids->get('language-2')]);
        static::assertSame((string) $dalProduct->getTranslation('description'), (string) $esProduct['description'][$ids->get('language-2')]);
        static::assertSame($dalProduct->getTranslation('customFields'), $esProduct['customFields'][Defaults::LANGUAGE_SYSTEM]);

        // Fetch: Second language variant fallback to parent
        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$ids->get('language-2'), $ids->get('language-1'), Defaults::LANGUAGE_SYSTEM]);
        $languageContext->addExtensions($context->getExtensions());
        $languageContext->setConsiderInheritance(true);

        $dal21 = $ids->getBytes('dal-2.1');

        $esProducts = $this->definition->fetch([$dal21], $languageContext);

        $esProduct = $esProducts[$ids->get('dal-2.1')];

        $criteria = new Criteria([$ids->get('dal-2.1')]);
        $dalProduct = $this->productRepository->search($criteria, $languageContext)->first();

        static::assertInstanceOf(ProductEntity::class, $dalProduct);
        static::assertSame((string) $dalProduct->getTranslation('name'), (string) $esProduct['name'][$ids->get('language-2')]);
        static::assertSame((string) $dalProduct->getTranslation('description'), (string) $esProduct['description'][$ids->get('language-1')]);
        static::assertSame($dalProduct->getTranslation('customFields'), $esProduct['customFields'][Defaults::LANGUAGE_SYSTEM]);

        // Fetch: Fallback through parent to variant in other language
        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$ids->get('language-3'), $ids->get('language-2'), Defaults::LANGUAGE_SYSTEM]);
        $languageContext->addExtensions($context->getExtensions());
        $languageContext->setConsiderInheritance(true);

        $dal22 = $ids->getBytes('dal-2.2');

        $esProducts = $this->definition->fetch([$dal22], $languageContext);

        $esProduct = $esProducts[$ids->get('dal-2.2')];

        $criteria = new Criteria([$ids->get('dal-2.2')]);
        $dalProduct = $this->productRepository->search($criteria, $languageContext)->first();

        static::assertInstanceOf(ProductEntity::class, $dalProduct);
        static::assertSame((string) $dalProduct->getTranslation('name'), (string) $esProduct['name'][$ids->get('language-2')]);
        static::assertSame((string) $dalProduct->getTranslation('description'), (string) $esProduct['description'][$ids->get('language-2')]);
        static::assertSame($dalProduct->getTranslation('customFields'), $esProduct['customFields'][Defaults::LANGUAGE_SYSTEM]);

        // Fetch: Fallback to parent on null-entry
        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$ids->get('language-1'), Defaults::LANGUAGE_SYSTEM]);
        $languageContext->addExtensions($context->getExtensions());
        $languageContext->setConsiderInheritance(true);

        $dal22 = $ids->getBytes('dal-2.2');

        $esProducts = $this->definition->fetch([$dal22], $languageContext);

        $esProduct = $esProducts[$ids->get('dal-2.2')];

        $criteria = new Criteria([$ids->get('dal-2.2')]);
        $dalProduct = $this->productRepository->search($criteria, $languageContext)->first();

        static::assertInstanceOf(ProductEntity::class, $dalProduct);
        static::assertSame((string) $dalProduct->getTranslation('name'), (string) $esProduct['name'][$ids->get('language-1')]);
        static::assertSame((string) $dalProduct->getTranslation('description'), (string) $esProduct['description'][$ids->get('language-1')]);
        static::assertSame($dalProduct->getTranslation('customFields'), $esProduct['customFields'][Defaults::LANGUAGE_SYSTEM]);
    }

    #[Depends('testIndexing')]
    public function testReleaseDate(IdsCollection $ids): void
    {
        $dal1 = $ids->getBytes('dal-1');

        $products = $this->definition->fetch([$dal1], $this->createIndexingContext());

        $product = $products[$ids->get('dal-1')];

        static::assertSame('2019-01-01T10:11:00+00:00', $product['releaseDate']);
    }

    #[Depends('testIndexing')]
    public function testProductSizeWidthHeightStockSales(IdsCollection $ids): void
    {
        $dal1 = $ids->getBytes('dal-1');

        $products = $this->definition->fetch([$dal1], $this->createIndexingContext());

        $product = $products[$ids->get('dal-1')];

        static::assertSame(12.3, $product['weight']);
        static::assertSame(9.3, $product['height']);
        static::assertSame(1.3, $product['width']);
        static::assertSame(2, $product['stock']);
        static::assertSame(0, $product['sales']);
    }

    #[Depends('testIndexing')]
    public function testCategoriesProperties(IdsCollection $ids): void
    {
        $dal1 = $ids->getBytes('dal-1');

        $products = $this->definition->fetch([$dal1], $this->createIndexingContext());

        $product = $products[$ids->get('dal-1')];
        $categoryIds = \array_column($product['categoriesRo'], 'id');

        static::assertContains($ids->get('c1'), $categoryIds);
        static::assertContains($ids->get('c2'), $categoryIds);

        static::assertContains($ids->get('red'), $product['propertyIds']);
        static::assertContains($ids->get('xl'), $product['propertyIds']);
    }

    #[Depends('testIndexing')]
    public function testCustomFieldsGetMapped(IdsCollection $ids): void
    {
        $mapping = $this->definition->getMapping($this->context);

        $languages = $this->languageRepository->searchIds(new Criteria(), $this->context)->getIds();

        $expected = [
            'properties' => [],
        ];

        foreach ($languages as $language) {
            static::assertIsString($language);
            $expected['properties'][$language] = [
                'type' => 'object',
                'dynamic' => true,
                'properties' => [
                    'test_bool' => [
                        'type' => 'boolean',
                    ],
                    'test_date' => [
                        'type' => 'date',
                        'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
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
                    'test_select' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                    'test_html' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                    'test_text' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                    'test_unmapped' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                    'testFloatingField' => [
                        'type' => 'double',
                    ],
                    'testField' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                    'a' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                    'b' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                    'c' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD,
                ],
            ];
        }

        static::assertEquals($expected, $mapping['properties']['customFields']);
    }

    #[Depends('testIndexing')]
    public function testSortByCustomFieldIntAsc(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addSorting(new FieldSorting('customFields.test_int', FieldSorting::ASCENDING));

            $searcher = $this->createEntitySearcher();

            $context->addState('test');

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('product-2'), $result[0]);
            static::assertSame($ids->get('product-1'), $result[1]);
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSortByCustomFieldIntDesc(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addSorting(new FieldSorting('customFields.test_int', FieldSorting::DESCENDING));
            $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));

            $searcher = $this->createEntitySearcher();

            $context->addState('test');

            /** @var array<string> $result */
            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('variant-3.1'), $result[0], (string) $ids->getKey($result[0])); // has 8000000000
            static::assertSame($ids->get('variant-3.2'), $result[1], (string) $ids->getKey($result[1])); // has 8000000000
            static::assertSame($ids->get('product-1'), $result[2], (string) $ids->getKey($result[2])); // has 19999
            static::assertSame($ids->get('product-2'), $result[3], (string) $ids->getKey($result[3])); // has 200
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCustomFieldsAreMerged(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsFilter('customFields.test_int', 8000000000));
            $criteria->addSorting(new FieldSorting('customFields.test_int', FieldSorting::ASCENDING));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertCount(2, $result);
            static::assertContains($ids->get('variant-3.2'), $result);
            static::assertContains($ids->get('variant-3.1'), $result);
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCustomFieldDateType(IdsCollection $ids): void
    {
        $context = $this->context;

        $searcher = $this->createEntitySearcher();

        try {
            $criteria = new EsAwareCriteria();
            $criteria->addSorting(new FieldSorting('customFields.test_date', FieldSorting::DESCENDING));
            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('product-1'), $result[0]);
            static::assertSame($ids->get('product-2'), $result[1]);

            $criteria = new EsAwareCriteria();
            $criteria->addFilter(new EqualsFilter('customFields.test_date', '2000-01-01 00:00:00.000'));
            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();
            static::assertContains($ids->get('product-2'), $result);

            $criteria = new EsAwareCriteria();
            $criteria->addFilter(new RangeFilter('customFields.test_date', ['gte' => '2000-01-01 00:00:00.000']));
            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();
            static::assertContains($ids->get('product-2'), $result);

            $criteria = new EsAwareCriteria();
            $criteria->addFilter(new EqualsFilter('customFields.test_date', '2000-01-01'));
            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();
            static::assertContains($ids->get('product-2'), $result);

            $criteria = new EsAwareCriteria();
            $criteria->addFilter(new EqualsFilter('customFields.test_date', '2000/01/01'));
            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();
            static::assertContains($ids->get('product-2'), $result);
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSortByPropertiesCount(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addSorting(new CountSorting('properties.id', CountSorting::DESCENDING));
            $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('dal-1'), $result[0]);
            static::assertSame($ids->get('dal-2.1'), $result[1]);
            static::assertSame($ids->get('dal-2.2'), $result[2]);
            static::assertSame($ids->get('product-1'), $result[3]);
            static::assertSame($ids->get('product-2'), $result[4]);
            static::assertSame($ids->get('product-3'), $result[5]);
            static::assertSame($ids->get('product-4'), $result[6]);
            static::assertSame($ids->get('zanother-product-3b'), $result[7]);
            static::assertSame($ids->get('cf1'), $result[8]);

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addSorting(new CountSorting('properties.id', CountSorting::ASCENDING));
            $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::DESCENDING));

            $result = array_reverse($searcher->search($this->productDefinition, $criteria, $context)->getIds());

            static::assertSame($ids->get('cf1'), $result[8]);
            static::assertSame($ids->get('zanother-product-3b'), $result[7]);
            static::assertSame($ids->get('product-4'), $result[6]);
            static::assertSame($ids->get('product-3'), $result[5]);
            static::assertSame($ids->get('product-2'), $result[4]);
            static::assertSame($ids->get('product-1'), $result[3]);
            static::assertSame($ids->get('dal-2.2'), $result[2]);
            static::assertSame($ids->get('dal-2.1'), $result[1]);
            static::assertSame($ids->get('dal-1'), $result[0]);
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFetchFloatedCustomFieldIds(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addAggregation(new TermsAggregation('testFloatingField', 'customFields.testFloatingField'));

            $aggregator = $this->createEntityAggregator();

            $result = $aggregator->aggregate($this->productDefinition, $criteria, $context)->get('testFloatingField');

            static::assertInstanceOf(TermsResult::class, $result);
            static::assertContains('1', $result->getKeys());
            static::assertContains('1.5', $result->getKeys());
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterByCustomFieldDate(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsFilter('customFields.test_date', '2000-01-01 00:00:00.000'));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertSame($ids->get('product-2'), $result[0]);
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterByStates(IdsCollection $ids): void
    {
        $context = $this->context;

        try {
            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addFilter(new EqualsAnyFilter('states', [State::IS_DOWNLOAD]));

            $searcher = $this->createEntitySearcher();

            $result = $searcher->search($this->productDefinition, $criteria, $context)->getIds();

            static::assertCount(1, $result);
            static::assertSame($ids->get('s-4'), $result[0]);
        } catch (\Exception $e) {
            $this->tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testEmptyEntityAggregation(IdsCollection $ids): void
    {
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addAggregation(new EntityAggregation('manufacturer', 'manufacturerId', 'product_manufacturer'));
        $result = $this->createEntityAggregator()->aggregate($this->productDefinition, $criteria, $this->context);

        static::assertTrue($result->has('manufacturer'));
        static::assertInstanceOf(EntityResult::class, $result->get('manufacturer'));
        $agg = $result->get('manufacturer');
        static::assertNotEmpty($agg->getEntities());

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        // p.13 has no assigned manufacturer, the aggregation should now return no manufacturers inside the collection
        $criteria->addFilter(new EqualsFilter('id', $ids->get('p.13')));
        $criteria->addAggregation(new EntityAggregation('manufacturer', 'manufacturerId', 'product_manufacturer'));
        $result = $this->createEntityAggregator()->aggregate($this->productDefinition, $criteria, $this->context);

        static::assertTrue($result->has('manufacturer'));
        static::assertInstanceOf(EntityResult::class, $result->get('manufacturer'));

        $agg = $result->get('manufacturer');
        static::assertEmpty($agg->getEntities());
    }

    #[Depends('testIndexing')]
    public function testVariantListingConfigShouldIndexMainProductWhenDisplayParentIsTrue(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('variant-1'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();
        static::assertCount(3, $result);
    }

    #[Depends('testIndexing')]
    public function testVariantListingConfigShouldNotIndexMainProductWhenDisplayParentIsFalse(IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed('variant-2'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();
        static::assertCount(2, $result);
    }

    #[Depends('testIndexing')]
    public function testRangeAggregation(IdsCollection $data): void
    {
        $rangesDefinition = [
            [],
            ['key' => 'all'],
            ['key' => 'custom_key', 'from' => 0, 'to' => 200],
            ['to' => 100],
            ['from' => 100, 'to' => 160],
            ['from' => 200, 'to' => 500],
            ['to' => 500],
        ];

        $rangesExpectedResult = [
            '*-*' => 7,
            'all' => 7,
            'custom_key' => 3,
            '*-100' => 2,
            '100-160' => 1,
            '200-500' => 4,
            '*-500' => 7,
        ];

        $aggregator = $this->createEntityAggregator();
        $criteria = new Criteria($data->prefixed('product-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addAggregation(new RangeAggregation('test-range-aggregation', 'product.stock', $rangesDefinition));

        $aggregations = $aggregator->aggregate($this->productDefinition, $criteria, $this->context);

        static::assertTrue($aggregations->has('test-range-aggregation'));
        static::assertInstanceOf(RangeResult::class, $aggregations->get('test-range-aggregation'));

        $rangesResult = $aggregations->get('test-range-aggregation')->getRanges();

        static::assertCount(\count($rangesDefinition), $rangesResult);
        foreach ($rangesResult as $key => $count) {
            static::assertArrayHasKey($key, $rangesExpectedResult);
            static::assertSame($rangesExpectedResult[$key], $count);
        }
    }

    #[Depends('testIndexing')]
    public function testFilterCoreDateFields(): void
    {
        $criteria = new EsAwareCriteria();
        $criteria->setLimit(1);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $this->createEntitySearcher()->search($this->productDefinition, $criteria, $this->context);

        $criteria = new EsAwareCriteria();
        $criteria->setLimit(1);
        $criteria->addSorting(new FieldSorting('releaseDate', FieldSorting::ASCENDING));
        $this->createEntitySearcher()->search($this->productDefinition, $criteria, $this->context);

        $criteria = new EsAwareCriteria();
        $criteria->addFilter(new EqualsFilter('releaseDate', '2019-01-01 10:11:00.000'));
        $result = $this->createEntitySearcher()->search($this->productDefinition, $criteria, $this->context);

        static::assertCount(4, $result->getIds());

        $criteria = new EsAwareCriteria();
        $criteria->addFilter(new EqualsFilter('createdAt', '2019-01-01 10:11:00.000'));
        $result = $this->createEntitySearcher()->search($this->productDefinition, $criteria, $this->context);

        static::assertCount(1, $result->getIds());

        // Test with non-zero ms
        $criteria = new EsAwareCriteria();
        $criteria->addFilter(new EqualsFilter('createdAt', '2019-01-01 10:11:00.123'));
        $result = $this->createEntitySearcher()->search($this->productDefinition, $criteria, $this->context);

        static::assertCount(1, $result->getIds());

        $criteria = new EsAwareCriteria();
        $criteria->addFilter(new EqualsFilter('releaseDate', '2019/01/01 10:11:00'));
        $result = $this->createEntitySearcher()->search($this->productDefinition, $criteria, $this->context);

        static::assertCount(4, $result->getIds());

        $criteria = new EsAwareCriteria();
        $criteria->addFilter(new EqualsFilter('createdAt', '2019/01/01 10:11:00'));
        $result = $this->createEntitySearcher()->search($this->productDefinition, $criteria, $this->context);

        static::assertCount(1, $result->getIds());
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    /**
     * @param array{ids: string[]} $case
     */
    private function assertSorting(string $message, IdsCollection $ids, SalesChannelContext $context, array $case, string $direction): void
    {
        $criteria = new Criteria(
            [...$ids->prefixed('p.'), ...$ids->prefixed('v.')]
        );
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $criteria->addSorting(new FieldSorting('product.cheapestPrice', $direction));
        $criteria->addSorting(new FieldSorting('product.productNumber', $direction));

        $criteria->addFilter(
            new OrFilter([
                new NandFilter([new EqualsFilter('product.parentId', null)]),
                new EqualsFilter('product.childCount', 0),
            ])
        );

        $searcher = $this->createEntitySearcher();
        $result = $searcher->search($this->productDefinition, $criteria, $context->getContext());

        $expected = $case['ids'];
        if ($direction === FieldSorting::DESCENDING) {
            $expected = array_reverse($expected);
        }

        $actual = array_values($result->getIds());

        foreach ($expected as $index => $key) {
            $id = $actual[$index];
            static::assertSame($ids->get($key), $id, \sprintf('Case `%s` failed for %s', $message, $key));
        }
    }

    /**
     * @return array<string, array{min: float, max: float, rules: string[]}>
     */
    private function providerCheapestPriceAggregation(): iterable
    {
        yield 'With no rules' => ['min' => 60.0, 'max' => 190.0, 'rules' => []];
        yield 'With rule a' => ['min' => 60.0, 'max' => 220.0, 'rules' => ['rule-a']];
        yield 'With rule b' => ['min' => 60.0, 'max' => 200.0, 'rules' => ['rule-b']];
        yield 'With rule a+b' => ['min' => 60.0, 'max' => 220.0, 'rules' => ['rule-a', 'rule-b']];
        yield 'With rule b+a' => ['min' => 60.0, 'max' => 220.0, 'rules' => ['rule-b', 'rule-a']];
    }

    private function createData(): void
    {
        $secondLanguage = $this->createLanguage();
        $this->ids->set('language-1', $secondLanguage);
        $thirdLanguage = $this->createLanguage($secondLanguage);
        $this->ids->set('language-2', $thirdLanguage);
        $fourthLanguage = $this->createLanguage();
        $this->ids->set('language-3', $fourthLanguage);
        $this->createSalesChannel(['id' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT]);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM custom_field');

        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFields = require __DIR__ . '/Fixture/CustomFields.php';

        $customFieldRepository->create([
            [
                'name' => 'swag_example_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'English custom field set label',
                        'de-DE' => 'German custom field set label',
                    ],
                ],
                'relations' => [
                    [
                        'entityName' => 'product',
                    ],
                ],
                'customFields' => $customFields,
            ],
        ], $this->context);

        $customMapping = \array_combine(\array_column($customFields, 'name'), \array_column($customFields, 'type'));

        ReflectionHelper::getProperty(ElasticsearchIndexingUtils::class, 'customFieldsTypes')->setValue(
            $this->utils,
            ['product' => $customMapping],
        );

        $products = ProductsFixture::get($this->ids, $secondLanguage, $thirdLanguage);

        $this->productRepository->create($products, $this->context);

        $products = [
            [
                'id' => $this->ids->get('variant-1'),
                'variantListingConfig' => [
                    'displayParent' => true,
                    'mainVariantId' => $this->ids->get('variant-1.1'),
                ],
            ],
            [
                'id' => $this->ids->get('variant-2'),
                'variantListingConfig' => [
                    'displayParent' => false,
                    'mainVariantId' => $this->ids->get('variant-2.1'),
                ],
            ],
        ];

        $this->productRepository->update($products, $this->context);
    }

    private function createLanguage(?string $parentId = null): string
    {
        $id = Uuid::randomHex();

        $languageRepository = static::getContainer()->get('language.repository');

        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'name' => \sprintf('name-%s', $id),
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'parentId' => $parentId,
                    'translationCode' => [
                        'code' => Uuid::randomHex(),
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'salesChannels' => [
                        ['id' => TestDefaults::SALES_CHANNEL],
                    ],
                    'salesChannelDefaultAssignments' => [
                        ['id' => TestDefaults::SALES_CHANNEL],
                    ],
                ],
            ],
            $this->context
        );

        return $id;
    }

    private function createIndexingContext(): Context
    {
        $context = $this->context;
        $context->addExtension('currencies', static::getContainer()->get('currency.repository')->search(new Criteria(), $this->context));

        return $context;
    }

    /**
     * Some tests use terms that are excluded by the default configuration in the administration.
     * Therefore we reset the configuration.
     */
    private function resetStopWords(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('UPDATE `product_search_config` SET `excluded_terms` = "[]"');
    }
}

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
class EsAwareCriteria extends Criteria
{
    public function __construct(?array $ids = null)
    {
        parent::__construct($ids);

        $this->addState(self::STATE_ELASTICSEARCH_AWARE);
    }
}
