<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
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
use Shopware\Core\Framework\Test\IdsCollection;
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
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @package system-settings
 */
#[Group('skip-paratest')]
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

    private EntityRepository $languageRepository;

    private ElasticsearchHelper $helper;

    private IdsCollection $ids;

    private Connection $connection;

    private EntityRepository $productRepository;

    private string $navigationId;

    private string $currencyId = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    private string $anotherCurrencyId = '2c962ddb7b3346f29c748a9d3b884302';

    private AbstractElasticsearchDefinition $definition;

    private ElasticsearchIndexingUtils $utils;

    private Context $context;

    protected function setUp(): void
    {
        $this->definition = $this->getContainer()->get(ElasticsearchProductDefinition::class);
        $this->utils = $this->getContainer()->get(ElasticsearchIndexingUtils::class);

        $this->helper = $this->getContainer()->get(ElasticsearchHelper::class);
        $this->client = $this->getContainer()->get(Client::class);
        $this->productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $this->languageRepository = $this->getContainer()->get('language.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->navigationId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)]
        );

        $this->registerDefinition(ExtendedProductDefinition::class);
        $this->registerDefinitionWithExtensions(ProductDefinition::class, ProductExtension::class);

        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->ids = new IdsCollection();
        $this->ids->set('navi', $this->navigationId);

        $this->context = Context::createDefaultContext();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->removeExtension(ProductExtension::class);

        parent::tearDown();
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

            $this->getContainer()
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
        return [
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                    ]
                ),
                ['s-1', 's-2', 's-3'],
            ],
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_ALL),
                    ]
                ),
                ['s-1', 's-4'],
            ],
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                        new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_LINK),
                    ]
                ),
                ['s-2'],
            ],
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_AND,
                    [
                        new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                        new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH),
                    ]
                ),
                ['s-3'],
            ],
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_OR,
                    [
                        new MultiFilter(
                            MultiFilter::CONNECTION_AND,
                            [
                                new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                                new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_ALL),
                            ]
                        ),
                        new MultiFilter(
                            MultiFilter::CONNECTION_AND,
                            [
                                new EqualsFilter('visibilities.salesChannelId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                                new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_LINK),
                            ]
                        ),
                    ]
                ),
                ['s-1', 's-3'],
            ],
            [
                new MultiFilter(
                    MultiFilter::CONNECTION_XOR,
                    [
                        new MultiFilter(
                            MultiFilter::CONNECTION_AND,
                            [
                                new EqualsFilter('visibilities.salesChannelId', TestDefaults::SALES_CHANNEL),
                                new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH),
                            ]
                        ),
                        new MultiFilter(
                            MultiFilter::CONNECTION_AND,
                            [
                                new EqualsFilter('visibilities.salesChannelId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                                new EqualsFilter('visibilities.visibility', ProductVisibilityDefinition::VISIBILITY_SEARCH),
                            ]
                        ),
                    ]
                ),
                ['s-2', 's-3'],
            ],
        ];
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

                static::assertSame(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));

                $term = strtolower($term);
                $products = $searcher->search($this->productDefinition, $criteria, $this->context);
                static::assertSame(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
                static::assertTrue($products->has($data->get('product-6')));

                $term = strtoupper($term);
                $products = $searcher->search($this->productDefinition, $criteria, $this->context);
                static::assertSame(1, $products->getTotal(), sprintf('Term "%s" do not match', $term));
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
            // case with time zone alias
            [
                new DateHistogramCase(DateHistogramAggregation::PER_DAY, [
                    '2019-01-01 00:00:00' => 2,
                    '2019-06-15 00:00:00' => 1,
                    '2020-09-30 00:00:00' => 1,
                    '2021-12-10 00:00:00' => 2,
                    '2024-12-12 00:00:00' => 1,
                ], null, 'Asia/Saigon'),
            ],
        ];
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

            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
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

            $result = $this->getContainer()->get(ProductListingRoute::class)
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

            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
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
                    static::assertTrue($result->has($ids->get($key)), sprintf('Missing id %s in case `%s`', $key, $message));
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
            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
                ->create(
                    Uuid::randomHex(),
                    TestDefaults::SALES_CHANNEL,
                    [
                        SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    ]
                );

            $cases = $this->providerCheapestPriceSorting();

            foreach ($cases as $message => $case) {
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
     * @return array<string, array{ids: string[], rules: string[]}>
     */
    public function providerCheapestPriceSorting(): iterable
    {
        yield 'Test sorting without rules' => [
            'ids' => [
                'v.4.1',
                'p.1',
                'v.4.2',
                'v.2.2',
                'v.2.1',
                'v.3.1',
                'v.3.2',
                'p.5',
                'v.6.1',
                'v.6.2',
                'v.7.1',
                'v.7.2',
                'v.8.1',
                'v.8.2',
                'v.10.2',
                'v.9.1',
                'v.10.1',
                'v.9.2',
                'v.11.1',
                'v.11.2',
                'v.12.1',
                'v.12.2',
                'v.13.1',
                'v.13.2',
            ],
            'rules' => [],
        ];

        yield 'Test sorting with rule a' => [
            'ids' => [
                'v.4.1',
                'p.1',
                'v.4.2',
                'v.2.2',
                'v.2.1',
                'v.3.1',
                'v.3.2',
                'p.5',
                'v.6.1',
                'v.6.2',
                'v.7.2',
                'v.10.2',
                'v.7.1',
                'v.10.1',
                'v.8.1',
                'v.9.1',
                'v.9.2',
                'v.8.2',
                'v.11.1',
                'v.11.2',
                'v.12.2',
                'v.12.1',
                'v.13.2',
                'v.13.1',
            ],
            'rules' => ['rule-a'],
        ];

        yield 'Test sorting with rule b' => [
            'ids' => [
                'v.4.1',
                'p.1',
                'v.4.2',
                'v.2.2',
                'v.2.1',
                'v.3.1',
                'v.3.2',
                'p.5',
                'v.6.1',
                'v.6.2',
                'v.7.1',
                'v.7.2',
                'v.8.1',
                'v.8.2',
                'v.10.2',
                'v.9.1',
                'v.10.1',
                'v.9.2',
                'v.12.1',
                'v.11.1',
                'v.11.2',
                'v.12.2',
                'v.13.1',
                'v.13.2',
            ],
            'rules' => ['rule-b'],
        ];

        yield 'Test sorting with rule a+b' => [
            'ids' => [
                'v.4.1',
                'p.1',
                'v.4.2',
                'v.2.2',
                'v.2.1',
                'v.3.1',
                'v.3.2',
                'p.5',
                'v.6.1',
                'v.6.2',
                'v.7.2',
                'v.10.2',
                'v.7.1',
                'v.10.1',
                'v.8.1',
                'v.9.1',
                'v.9.2',
                'v.8.2',
                'v.11.1',
                'v.11.2',
                'v.12.2',
                'v.12.1',
                'v.13.2',
                'v.13.1',
            ],
            'rules' => ['rule-a', 'rule-b'],
        ];

        yield 'Test sorting with rule b+a' => [
            'ids' => [
                'v.4.1',
                'p.1',
                'v.4.2',
                'v.2.2',
                'v.2.1',
                'v.3.1',
                'v.3.2',
                'p.5',
                'v.6.1',
                'v.6.2',
                'v.7.2',
                'v.10.2',
                'v.7.1',
                'v.10.1',
                'v.8.1',
                'v.9.1',
                'v.9.2',
                'v.8.2',
                'v.11.1',
                'v.11.2',
                'v.12.2',
                'v.13.2',
                'v.12.1',
                'v.13.1',
            ],
            'rules' => ['rule-b', 'rule-a'],
        ];
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
                static::assertSame($case['min'], $aggregation->getMin(), sprintf('Case `%s` failed', $message));
                static::assertSame($case['max'], $aggregation->getMax(), sprintf('Case `%s` failed', $message));
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
            $context = $this->getContainer()->get(SalesChannelContextFactory::class)
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

                static::assertCount(is_countable($case['ids']) ? \count($case['ids']) : 0, $result->getIds(), sprintf('Case `%s` failed', $message));
                static::assertSame(array_map(fn (string $id) => $ids->get($id), $case['ids']), $result->getIds(), sprintf('Case `%s` failed', $message));
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
            static::assertSame($ids->get('cf1'), $result[7]);

            $criteria = new Criteria();
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $criteria->addSorting(new CountSorting('properties.id', CountSorting::ASCENDING));
            $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::DESCENDING));

            $result = array_reverse($searcher->search($this->productDefinition, $criteria, $context)->getIds());

            static::assertSame($ids->get('cf1'), $result[7]);
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
    #[DataProvider('variantListingConfigProvider')]
    public function testVariantListingConfig(string $productIds, int $expected, IdsCollection $ids): void
    {
        $criteria = new Criteria($ids->prefixed($productIds));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();
        static::assertCount($expected, $result);
    }

    /**
     * @return array<string, array{productIds: string, expected: int}>
     */
    public static function variantListingConfigProvider(): iterable
    {
        yield 'Should index main product when displayParent is true' => ['productIds' => 'variant-1', 'expected' => 3];
        yield 'Should not index main product when displayParent is false' => ['productIds' => 'variant-2', 'expected' => 2];
    }

    /**
     * @return array<string, array{rangesDefinition: mixed, rangesExpectedResult: mixed}>
     */
    public static function rangeAggregationDataProvider(): iterable
    {
        yield 'default ranges test cases' => [
            'rangesDefinition' => [
                [],
                ['key' => 'all'],
                ['key' => 'custom_key', 'from' => 0, 'to' => 200],
                ['to' => 100],
                ['from' => 100, 'to' => 160],
                ['from' => 200, 'to' => 500],
                ['to' => 500],
            ],
            'rangesExpectedResult' => [
                '*-*' => 7,
                'all' => 7,
                'custom_key' => 3,
                '*-100' => 2,
                '100-160' => 1,
                '200-500' => 4,
                '*-500' => 7,
            ],
        ];
    }

    /**
     * @param array<int, array<string, string|float>> $rangesDefinition
     * @param array<string, int> $rangesExpectedResult
     */
    #[Depends('testIndexing')]
    #[DataProvider('rangeAggregationDataProvider')]
    public function testRangeAggregation(array $rangesDefinition, array $rangesExpectedResult, IdsCollection $data): void
    {
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
        return $this->getContainer();
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
            static::assertSame($ids->get($key), $id, sprintf('Case `%s` failed for %s', $message, $key));
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

        $this->getContainer()->get(Connection::class)->executeStatement('DELETE FROM custom_field');

        $customFieldRepository = $this->getContainer()->get('custom_field_set.repository');

        $customFields = [
            [
                'name' => 'a',
                'type' => CustomFieldTypes::TEXT,
            ],
            [
                'name' => 'b',
                'type' => CustomFieldTypes::TEXT,
            ],
            [
                'name' => 'c',
                'type' => CustomFieldTypes::TEXT,
            ],
            [
                'name' => 'test_int',
                'type' => CustomFieldTypes::INT,
            ],
            [
                'name' => 'testFloatingField',
                'type' => CustomFieldTypes::FLOAT,
            ],
            [
                'name' => 'testField',
                'type' => CustomFieldTypes::TEXT,
            ],
            [
                'name' => 'test_select',
                'type' => CustomFieldTypes::SELECT,
            ],
            [
                'name' => 'test_text',
                'type' => CustomFieldTypes::TEXT,
            ],
            [
                'name' => 'test_html',
                'type' => CustomFieldTypes::HTML,
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
        ];

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

        $products = [
            (new ProductBuilder($this->ids, 'product-1'))
                ->name('Silk')
                ->category('navi')
                ->customField('testField', 'Silk')
                ->visibility()
                ->tax('t1')
                ->manufacturer('m1')
                ->price(50, 50, 'default', 150, 150)
                ->releaseDate('2019-01-01 10:11:00')
                ->purchasePrice(0)
                ->stock(2)
                ->createdAt('2019-01-01 10:11:00')
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100, 100, 'default', 150, 150)
                ->price(300, null, 'anotherCurrency')
                ->releaseDate('2019-01-01 10:13:00')
                ->createdAt('2019-01-02 10:11:00')
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t2')
                ->manufacturer('m2')
                ->price(150, 150, 'default', 150, 150)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t2')
                ->manufacturer('m2')
                ->price(200, 200, 'default', 500, 500)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->manufacturer('m3')
                ->price(250, 250, 'default', 300, 300)
                ->price(600, null, 'anotherCurrency')
                ->releaseDate('2021-12-10 11:59:00')
                ->purchasePrice(100)
                ->stock(300)
                ->build(),
            (new ProductBuilder($this->ids, 'product-6'))
                ->name('Spachtelmasse of some awesome company')
                ->category('navi')
                ->customField('testField', 'Spachtelmasse')
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t3')
                ->price(300)
                ->releaseDate('2024-12-11 23:59:00')
                ->stock(350)
                ->build(),
            (new ProductBuilder($this->ids, 'n7'))
                ->name('Other product')
                ->category('navi')
                ->customField('testField', 'Other product')
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m2')
                ->price(100)
                ->releaseDate('2019-01-01 10:13:00')
                ->purchasePrice(0)
                ->stock(10)
                ->category('cs1')
                ->build(),
            (new ProductBuilder($this->ids, 'cf1'))
                ->name('CF')
                ->category('navi')
                ->customField(
                    'test_text',
                    'Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero, non adipiscing dolor urna a orci. Nulla porta dolor. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. Pellentesque. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero, non adipiscing dolor urna a orci. Nulla porta dolor. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. PellentesqueLorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero, non adipiscing dolor urna a orci. Nulla porta dolor. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos hymenaeos. Pellentesque. Lorem ipsum dolor sit amet, consectetuer adipiscing elit. Aenean commodo ligula eget dolor. Aenean massa. Cum sociis natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Donec quam felis, ultricies nec, pellentesque eu, pretium quis, sem. Nulla consequat massa quis enim. Donec pede justo, fringilla vel, aliquet nec, vulputate eget, arcu. In enim justo, rhoncus ut, imperdiet a, venenatis vitae, justo. Nullam dictum felis eu pede mollis pretium. Integer tincidunt. Cras dapibus. Vivamus elementum semper nisi. Aenean vulputate eleifend tellus. Aenean leo ligula, porttitor eu, consequat vitae, eleifend ac, enim. Aliquam lorem ante, dapibus in, viverra quis, feugiat a, tellus. Phasellus viverra nulla ut metus varius laoreet. Quisque rutrum. Aenean imperdiet. Etiam ultricies nisi vel augue. Curabitur ullamcorper ultricies nisi. Nam eget dui. Etiam rhoncus. Maecenas tempus, tellus eget condimentum rhoncus, sem quam semper libero, sit amet adipiscing sem neque sed ipsum. Nam quam nunc, blandit vel, luctus pulvinar, hendrerit id, lorem. Maecenas nec odio et ante tincidunt tempus. Donec vitae sapien ut libero venenatis faucibus. Nullam quis ante. Etiam sit amet orci eget eros faucibus tincidunt. Duis leo. Sed fringilla mauris sit amet nibh. Donec sodales sagittis magna. Sed consequat, leo eget bibendum sodales, augue velit cursus nunc, quis gravida magna mi a libero. Fusce vulputate eleifend sapien. Vestibulum purus quam, scelerisque ut, mollis sed, nonummy id, metus. Nullam accumsan lorem in dui. Cras ultricies mi eu turpis hendrerit fringilla. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; In ac dui quis mi consectetuer lacinia. Nam pretium turpis et arcu. Duis arcu tortor, suscipit eget, imperdiet nec, imperdiet iaculis, ipsum. Sed aliquam ultrices mauris. Integer ante arcu, accumsan a, consectetuer eget, posuere ut, mauris. Praesent adipiscing. Phasellus ullamcorper ipsum rutrum nunc. Nunc nonummy metus. Vestibulum volutpat pretium libero. Cras id dui. Aenean ut eros et nisl sagittis vestibulum. Nullam nulla eros, ultricies sit amet, nonummy id, imperdiet feugiat, pede. Sed lectus. Donec mollis hendrerit risus. Phasellus nec sem in justo pellentesque facilisis. Etiam imperdiet imperdiet orci. Nunc nec neque. Phasellus leo dolor, tempus non, auctor et, hendrerit quis, nisi. Curabitur ligula sapien, tincidunt non, euismod vitae, posuere imperdiet, leo. Maecenas malesuada. Praesent congue erat at massa. Sed cursus turpis vitae tortor. Donec posuere vulputate arcu. Phasellus accumsan cursus velit. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Sed aliquam, nisi quis porttitor congue, elit erat euismod orci, ac placerat dolor lectus quis orci. Phasellus consectetuer vestibulum elit. Aenean tellus metus, bibendum sed, posuere ac, mattis non, nunc. Vestibulum fringilla pede sit amet augue. In turpis. Pellentesque posuere. Praesent turpis. Aenean posuere, tortor sed cursus feugiat, nunc augue blandit nunc, eu sollicitudin urna dolor sagittis lacus. Donec elit libero, sodales nec, volutpat a, suscipit non, turpis. Nullam sagittis. Suspendisse pulvinar, augue ac venenatis condimentum, sem libero volutpat nibh, nec pellentesque velit pede quis nunc. Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia Curae; Fusce id purus. Ut varius tincidunt libero. Phasellus dolor. Maecenas vestibulum mollis diam. Pellentesque ut neque. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. In dui magna, posuere eget, vestibulum et, tempor auctor, justo. In ac felis quis tortor malesuada pretium. Pellentesque auctor neque nec urna. Proin sapien ipsum, porta a, auctor quis, euismod ut, mi. Aenean viverra rhoncus pede. Pellentesque habitant morbi tristique senectus et netus et malesuada fames ac turpis egestas. Ut non enim eleifend felis pretium feugiat. Vivamus quis mi. Phasellus a est. Phasellus magna. In hac habitasse platea dictumst. Curabitur at lacus ac velit ornare lobortis. Curabitur a felis in nunc fringilla tristique. Morbi mattis ullamcorper velit. Phasellus gravida semper nisi. Nullam vel sem. Pellentesque libero tortor, tincidunt et, tincidunt eget, semper nec, quam. Sed hendrerit. Morbi ac felis. Nunc egestas, augue at pellentesque laoreet, felis eros vehicula leo, at malesuada velit leo quis pede. Donec interdum, metus et hendrerit aliquet, dolor diam sagittis ligula, eget egestas libero turpis vel mi. Nunc nulla. Fusce risus nisl, viverra et, tempor et, pretium in, sapien. Donec venenatis vulputate lorem. Morbi nec metus. Phasellus blandit leo ut odio. Maecenas ullamcorper, dui et placerat feugiat, eros pede varius nisi, condimentum viverra felis nunc et lorem. Sed magna purus, fermentum eu, tincidunt eu, varius ut, felis. In auctor lobortis lacus. Quisque libero metus, condimentum nec, tempor a, commodo mollis, magna. Vestibulum ullamcorper mauris at ligula. Fusce fermentum. Nullam cursus lacinia erat. Praesent blandit laoreet nibh. Fusce convallis metus id felis luctus adipiscing. Pellentesque egestas, neque sit amet convallis pulvinar, justo nulla eleifend augue, ac auctor orci leo non est. Quisque id mi. Ut tincidunt tincidunt erat. Etiam feugiat lorem non metus. Vestibulum dapibus nunc ac augue. Curabitur vestibulum aliquam leo. Praesent egestas neque eu enim. In hac habitasse platea dictumst. Fusce a quam. Etiam ut purus mattis mauris sodales aliquam. Curabitur nisi. Quisque malesuada placerat nisl. Nam ipsum risus, rutrum vitae, vestibulum eu, molestie vel, lacus. Sed augue ipsum, egestas nec, vestibulum et, malesuada adipiscing, dui. Vestibulum facilisis, purus nec pulvinar iaculis, ligula mi congue nunc, vitae euismod ligula urna in dolor. Mauris sollicitudin fermentum libero. Praesent nonummy mi in odio. Nunc interdum lacus sit amet orci. Vestibulum rutrum, mi nec elementum vehicula, eros quam gravida nisl, id fringilla neque ante vel mi. Morbi mollis tellus ac sapien. Phasellus volutpat, metus eget egestas mollis, lacus lacus blandit dui, id egestas quam mauris ut lacus. Fusce vel dui. Sed in libero ut nibh placerat accumsan. Proin faucibus arcu quis ante. In consectetuer turpis ut velit. Nulla sit amet est. Praesent metus tellus, elementum eu, semper a, adipiscing nec, purus. Cras risus ipsum, faucibus ut, ullamcorper id, varius ac, leo. Suspendisse feugiat. Suspendisse enim turpis, dictum sed, iaculis a, condimentum nec, nisi. Praesent nec nisl a purus blandit viverra. Praesent ac massa at ligula laoreet iaculis. Nulla neque dolor, sagittis eget, iaculis quis, molestie non, velit. Mauris turpis nunc, blandit et, volutpat molestie, porta ut, ligula. Fusce pharetra convallis urna. Quisque ut nisi. Donec mi odio, faucibus at, scelerisque quis, convallis in, nisi. Suspendisse non nisl sit amet velit hendrerit rutrum. Ut leo. Ut a nisl id ante tempus hendrerit. Proin pretium, leo ac pellentesque mollis, felis nunc ultrices eros, sed gravida augue augue mollis justo. Suspendisse eu ligula. Nulla facilisi. Donec id justo. Praesent porttitor, nulla vitae posuere iaculis, arcu nisl dignissim dolor, a pretium mi sem ut ipsum. Curabitur suscipit suscipit tellus. Praesent vestibulum dapibus nibh. Etiam iaculis nunc ac metus. Ut id nisl quis enim dignissim sagittis. Etiam sollicitudin, ipsum eu pulvinar rutrum, tellus ipsum laoreet sapien, quis venenatis ante odio sit amet eros. Proin magna. Duis vel nibh at velit scelerisque suscipit. Curabitur turpis. Vestibulum suscipit nulla quis orci. Fusce ac felis sit amet ligula pharetra condimentum. Maecenas egestas arcu quis ligula mattis placerat. Duis lobortis massa imperdiet quam. Suspendisse potenti. Pellentesque commodo eros a enim. Vestibulum turpis sem, aliquet eget, lobortis pellentesque, rutrum eu, nisl. Sed libero. Aliquam erat volutpat. Etiam vitae tortor. Morbi vestibulum volutpat enim. Aliquam eu nunc. Nunc sed turpis. Sed mollis, eros et ultrices tempus, mauris ipsum aliquam libero'
                )
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->build(),

            // no rule = 79€
            (new ProductBuilder($this->ids, 'p.2'))
                ->price(80)
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->build(),

            // no rule = 120€  ||  rule-a = 130€
            (new ProductBuilder($this->ids, 'p.6'))
                ->price(120)
                ->prices('rule-a', 150)
                ->prices('rule-a', 140, 'default', null, 3)
                ->prices('rule-a', 199, 'currency')
                ->prices('rule-a', 188, 'currency', null, 3)
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'v.11.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'v.11.2'))
                        ->build()
                )
                ->build(),

            // no rule = 180 ||  rule-a = 210  || rule-b = 180 || a+b = 210 || b+a = 210/190
            (new ProductBuilder($this->ids, 'p.12'))
                ->price(180)
                ->visibility(TestDefaults::SALES_CHANNEL)
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

            // no rule = 190 ||  rule-a = 220  || rule-b = 190 || a+b = 220 || b+a = 220/200
            (new ProductBuilder($this->ids, 'p.13'))
                ->price(190)
                ->visibility(TestDefaults::SALES_CHANNEL)
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
                ->visibility(TestDefaults::SALES_CHANNEL)
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

            (new ProductBuilder($this->ids, 'dal-2'))
                ->name('Default')
                ->category('pants')
                ->customField('testField', 'Silk')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->tax('t1')
                ->manufacturer('m1')
                ->price(60)
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
                ->variant(
                    (new ProductBuilder($this->ids, 'dal-2.1'))
                        ->translation($secondLanguage, 'name', 'Variant 1 Second')
                        ->translation($secondLanguage, 'description', 'Variant 1 Second Desc')
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'dal-2.2'))
                        ->translation($secondLanguage, 'name', null)
                        ->translation($secondLanguage, 'description', 'Variant 2 Second Desc')
                        ->translation($thirdLanguage, 'name', 'Variant 2 Third')
                        ->translation($thirdLanguage, 'description', 'Variant 2 Third Desc')
                        ->build()
                )
                ->build(),
            (new ProductBuilder($this->ids, 'dal-3'))
                ->price(50)
                ->customField('a', '1')
                ->translation($secondLanguage, 'customFields', ['a' => '2', 'b' => '1'])
                ->translation($thirdLanguage, 'customFields', ['a' => '3', 'b' => '2', 'c' => '1'])
                ->build(),

            (new ProductBuilder($this->ids, 's-1'))
                ->name('Default-1')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
                ->build(),
            (new ProductBuilder($this->ids, 's-2'))
                ->name('Default-2')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_LINK)
                ->visibility(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, ProductVisibilityDefinition::VISIBILITY_SEARCH)
                ->build(),
            (new ProductBuilder($this->ids, 's-3'))
                ->name('Default-3')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_SEARCH)
                ->visibility(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, ProductVisibilityDefinition::VISIBILITY_LINK)
                ->build(),
            (new ProductBuilder($this->ids, 's-4'))
                ->name('Default-4')
                ->price(1)
                ->visibility(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, ProductVisibilityDefinition::VISIBILITY_ALL)
                ->add('downloads', [
                    [
                        'media' => [
                            'fileName' => 'foo',
                            'fileExtension' => 'bar',
                            'private' => true,
                        ],
                    ],
                ])
                ->build(),
            (new ProductBuilder($this->ids, 'variant-1'))
                ->name('Main-Product-1')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-1.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-1.2'))
                        ->build()
                )
                ->build(),
            (new ProductBuilder($this->ids, 'variant-2'))
                ->name('Main-Product-2')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-2.1'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-2.2'))
                        ->build()
                )
                ->build(),
            (new ProductBuilder($this->ids, 'variant-3'))
                ->name('Main-Product-2')
                ->price(1)
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->customField('test_int', 8000000000)
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-3.1'))
                        ->customField('random', 1)
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($this->ids, 'variant-3.2'))
                        ->customField('random', 1)
                        ->build()
                )
                ->build(),
            (new ProductBuilder($this->ids, 'sort.glumanda'))
                ->tag('shopware')
                ->price(1)
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'sort.bisasam'))
                ->tag('amazon')
                ->price(1)
                ->visibility()
                ->build(),
            (new ProductBuilder($this->ids, 'sort.pikachu'))
                ->tag('zalando')
                ->price(1)
                ->visibility()
                ->build(),
        ];

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
        $context->addExtension('currencies', $this->getContainer()->get('currency.repository')->search(new Criteria(), $this->context));

        return $context;
    }

    /**
     * Some tests use terms that are excluded by the default configuration in the administration.
     * Therefore we reset the configuration.
     */
    private function resetStopWords(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->executeStatement('UPDATE `product_search_config` SET `excluded_terms` = "[]"');
    }
}

/**
 * @internal
 */
class EsAwareCriteria extends Criteria
{
    public function __construct(?array $ids = null)
    {
        parent::__construct($ids);

        $this->addState(self::STATE_ELASTICSEARCH_AWARE);
    }
}
