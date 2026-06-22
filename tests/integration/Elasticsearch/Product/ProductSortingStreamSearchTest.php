<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class ProductSortingStreamSearchTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private Client $client;

    private ProductDefinition $productDefinition;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private ElasticsearchHelper $helper;

    private ElasticsearchOutdatedIndexDetector $indexDetector;

    private IdsCollection $ids;

    private Connection $connection;

    private Context $context;

    /**
     * Built once for the whole class by the first run of setUp(). The first-test-indexes pattern was
     * replaced by guarded setUp so the suite no longer depends on test execution order.
     */
    private static IdsCollection $indexedIds;

    protected function setUp(): void
    {
        $this->helper = static::getContainer()->get(ElasticsearchHelper::class);
        $this->client = static::getContainer()->get(Client::class);
        $this->productDefinition = static::getContainer()->get(ProductDefinition::class);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->indexDetector = static::getContainer()->get(ElasticsearchOutdatedIndexDetector::class);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        parent::setUp();

        if (!isset(self::$indexedIds)) {
            self::$indexedIds = $this->buildIndex();
        }
    }

    #[AfterClass]
    public static function cleanup(): void
    {
        $container = KernelLifecycleManager::getKernel()->getContainer();

        $connection = $container->get(Connection::class);

        $connection->executeStatement('DELETE FROM product');

        $connection->executeStatement(
            'DELETE FROM product_stream WHERE id IN (SELECT product_stream_id FROM product_stream_translation WHERE name LIKE :name)',
            ['name' => 'Custom Field %Stream%']
        );

        $connection->executeStatement('DELETE FROM product_sorting WHERE url_key LIKE :key', ['key' => 'ss-test-%']);

        $connection->executeStatement('DELETE FROM custom_field WHERE name LIKE :name', ['name' => 'ss\_test\_%']);
        $connection->executeStatement('DELETE FROM custom_field_set WHERE name = :name', ['name' => 'sorting_stream_search_set']);

        $connection->executeStatement('DELETE FROM elasticsearch_index_task');
    }

    public function testCustomFieldMappingsExist(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $allIndices = $this->indexDetector->getAllUsedIndices();
        static::assertNotEmpty($allIndices, 'No ES indices found. Keys: ' . implode(', ', array_keys($allIndices)));

        $indexName = array_keys($allIndices)[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        static::assertArrayHasKey(
            Defaults::LANGUAGE_SYSTEM,
            $properties,
            'Language system key not found. Available keys: ' . implode(', ', array_keys($properties))
        );
        $languageProperties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'];
        static::assertIsArray($languageProperties);

        static::assertArrayHasKey('ss_test_int', $languageProperties);
        static::assertSame('long', $languageProperties['ss_test_int']['type']);

        static::assertArrayHasKey('ss_test_float', $languageProperties);
        static::assertSame('double', $languageProperties['ss_test_float']['type']);

        static::assertArrayHasKey('ss_test_text', $languageProperties);
        static::assertSame('keyword', $languageProperties['ss_test_text']['type']);

        static::assertArrayHasKey('ss_test_bool', $languageProperties);
        static::assertSame('boolean', $languageProperties['ss_test_bool']['type']);
    }

    public function testSortByCustomFieldIntAsc(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('customFields.ss_test_int', FieldSorting::ASCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();

        // Expected order by ss_test_int ASC: product-4 (50), product-1 (100), product-2 (200), product-3 (300)
        static::assertSame($ids->get('product-4'), $result[0]);
        static::assertSame($ids->get('product-1'), $result[1]);
        static::assertSame($ids->get('product-2'), $result[2]);
        static::assertSame($ids->get('product-3'), $result[3]);
    }

    public function testSortByCustomFieldIntDesc(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('customFields.ss_test_int', FieldSorting::DESCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();

        // Expected order by ss_test_int DESC: product-3 (300), product-2 (200), product-1 (100), product-4 (50)
        static::assertSame($ids->get('product-3'), $result[0]);
        static::assertSame($ids->get('product-2'), $result[1]);
        static::assertSame($ids->get('product-1'), $result[2]);
        static::assertSame($ids->get('product-4'), $result[3]);
    }

    public function testSortByCustomFieldFloatDesc(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('customFields.ss_test_float', FieldSorting::DESCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();

        // Expected order by ss_test_float DESC: product-4 (3.5), product-2 (2.5), product-1 (1.5), product-3 (0.5)
        static::assertSame($ids->get('product-4'), $result[0]);
        static::assertSame($ids->get('product-2'), $result[1]);
        static::assertSame($ids->get('product-1'), $result[2]);
        static::assertSame($ids->get('product-3'), $result[3]);
    }

    public function testFilterByCustomFieldTextEquals(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_text', 'alpha'));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        // product-1 and product-3 have ss_test_text = 'alpha'
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-3')));
    }

    public function testFilterByCustomFieldBoolTrue(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_bool', true));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        // product-1 and product-3 have ss_test_bool = true
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-3')));
    }

    public function testFilterByCustomFieldBoolFalse(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_bool', false));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        // product-2 and product-4 have ss_test_bool = false
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-2')));
        static::assertTrue($result->has($ids->get('product-4')));
    }

    public function testFilterByCustomFieldIntRange(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new RangeFilter('customFields.ss_test_int', [
            RangeFilter::GTE => 100,
            RangeFilter::LTE => 200,
        ]));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        // product-1 (100) and product-2 (200) are in range [100, 200]
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-2')));
    }

    public function testFilterByCustomFieldIntExact(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_int', 300));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        // Only product-3 has ss_test_int = 300
        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-3')));
    }

    public function testFilterByCustomFieldFloatRange(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new RangeFilter('customFields.ss_test_float', [
            RangeFilter::GT => 1.0,
            RangeFilter::LT => 3.0,
        ]));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context);

        // product-1 (1.5) and product-2 (2.5) are in range (1.0, 3.0)
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('product-1')));
        static::assertTrue($result->has($ids->get('product-2')));
    }

    public function testCombinedFilterAndSort(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_text', 'alpha'));
        $criteria->addSorting(new FieldSorting('customFields.ss_test_int', FieldSorting::ASCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();

        // Filtered to alpha (product-1, product-3), sorted by int ASC (100, 300)
        static::assertCount(2, $result);
        static::assertSame($ids->get('product-1'), $result[0]);
        static::assertSame($ids->get('product-3'), $result[1]);
    }

    public function testCombinedBoolFilterAndFloatSort(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_bool', false));
        $criteria->addSorting(new FieldSorting('customFields.ss_test_float', FieldSorting::ASCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->productDefinition, $criteria, $this->context)->getIds();

        // Filtered to bool=false (product-2, product-4), sorted by float ASC (2.5, 3.5)
        static::assertCount(2, $result);
        static::assertSame($ids->get('product-2'), $result[0]);
        static::assertSame($ids->get('product-4'), $result[1]);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }

    private function buildIndex(): IdsCollection
    {
        $this->connection->executeStatement('DELETE FROM product');

        $this->clearElasticsearch();

        $this->connection->executeStatement('DELETE FROM product_sorting WHERE url_key LIKE :key', ['key' => 'ss-test-%']);
        $this->connection->executeStatement(
            'DELETE FROM product_stream WHERE id IN (SELECT product_stream_id FROM product_stream_translation WHERE name LIKE :name)',
            ['name' => 'Custom Field %Stream%']
        );
        $this->connection->executeStatement('DELETE FROM custom_field');

        // Create the ES index first (empty, no custom fields yet)
        $command = new ElasticsearchIndexingCommand(
            static::getContainer()->get(ElasticsearchIndexer::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(CreateAliasTaskHandler::class),
            true
        );

        $command->run(new ArrayInput([]), new NullOutput());

        static::assertNotEmpty($this->indexDetector->getAllUsedIndices());

        // Create custom fields
        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('custom-field-set'),
                'name' => 'sorting_stream_search_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'Sorting/Stream Search Test Set',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        'name' => 'ss_test_int',
                        'type' => CustomFieldTypes::INT,
                    ],
                    [
                        'name' => 'ss_test_float',
                        'type' => CustomFieldTypes::FLOAT,
                    ],
                    [
                        'name' => 'ss_test_text',
                        'type' => CustomFieldTypes::TEXT,
                    ],
                    [
                        'name' => 'ss_test_bool',
                        'type' => CustomFieldTypes::BOOL,
                    ],
                ],
            ],
        ], $this->context);

        $sortingRepository = static::getContainer()->get('product_sorting.repository');
        $sortingRepository->create([
            [
                'id' => $this->ids->get('sorting-by-int'),
                'key' => 'ss-test-sorting-int',
                'priority' => 1,
                'active' => true,
                'fields' => [
                    ['field' => 'customFields.ss_test_int', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ],
                'label' => 'Sort by ss_test_int',
            ],
            [
                'id' => $this->ids->get('sorting-by-float'),
                'key' => 'ss-test-sorting-float',
                'priority' => 2,
                'active' => true,
                'fields' => [
                    ['field' => 'customFields.ss_test_float', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => false],
                ],
                'label' => 'Sort by ss_test_float',
            ],
        ], $this->context);

        $productStreamRepository = static::getContainer()->get('product_stream.repository');
        $productStreamRepository->create([
            [
                'id' => $this->ids->get('stream-1'),
                'name' => 'Custom Field Bool Stream',
                'filters' => [
                    [
                        'id' => $this->ids->get('stream-filter-1'),
                        'type' => 'equals',
                        'field' => 'customFields.ss_test_bool',
                        'value' => '1',
                    ],
                ],
            ],
            [
                'id' => $this->ids->get('stream-2'),
                'name' => 'Custom Field Text Stream',
                'filters' => [
                    [
                        'id' => $this->ids->get('stream-filter-2'),
                        'type' => 'equals',
                        'field' => 'customFields.ss_test_text',
                        'value' => 'alpha',
                    ],
                ],
            ],
        ], $this->context);

        // Reset the custom field types cache so the next getCustomFieldTypes() call
        // queries the DB (the cache was populated as empty during index creation before custom fields existed)
        $utils = static::getContainer()->get(ElasticsearchIndexingUtils::class);
        (new \ReflectionProperty(ElasticsearchIndexingUtils::class, 'customFieldsTypes'))->setValue($utils, []);

        $products = [
            (new ProductBuilder($this->ids, 'product-1'))
                ->name('Product Alpha')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->price(100)
                ->stock(10)
                ->customField('ss_test_int', 100)
                ->customField('ss_test_float', 1.5)
                ->customField('ss_test_text', 'alpha')
                ->customField('ss_test_bool', true)
                ->build(),
            (new ProductBuilder($this->ids, 'product-2'))
                ->name('Product Beta')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->price(200)
                ->stock(20)
                ->customField('ss_test_int', 200)
                ->customField('ss_test_float', 2.5)
                ->customField('ss_test_text', 'beta')
                ->customField('ss_test_bool', false)
                ->build(),
            (new ProductBuilder($this->ids, 'product-3'))
                ->name('Product Gamma')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->price(300)
                ->stock(30)
                ->customField('ss_test_int', 300)
                ->customField('ss_test_float', 0.5)
                ->customField('ss_test_text', 'alpha')
                ->customField('ss_test_bool', true)
                ->build(),
            (new ProductBuilder($this->ids, 'product-4'))
                ->name('Product Delta')
                ->visibility(TestDefaults::SALES_CHANNEL)
                ->price(400)
                ->stock(40)
                ->customField('ss_test_int', 50)
                ->customField('ss_test_float', 3.5)
                ->customField('ss_test_text', 'delta')
                ->customField('ss_test_bool', false)
                ->build(),
        ];

        $this->productRepository->create($products, $this->context);

        // Index the products into ES using updateIds
        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        $indexer->updateIds(
            $this->productDefinition,
            [
                $this->ids->get('product-1'),
                $this->ids->get('product-2'),
                $this->ids->get('product-3'),
                $this->ids->get('product-4'),
            ]
        );

        $this->refreshIndex();

        $index = $this->helper->getIndexName($this->productDefinition);

        $exists = $this->client->indices()->exists(['index' => $index]);
        static::assertTrue($exists, 'Expected elasticsearch indices present');

        return $this->ids;
    }
}
