<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
class ProductSearchQueryBuilderTest extends TestCase
{
    use CacheTestBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private Connection $connection;

    private CustomFieldService $customFieldService;

    /**
     * Built once for the whole class by the first run of setUp(). The first-test-indexes pattern was
     * replaced by guarded setUp because a data-provided test (testSearch) can no longer also receive
     * the ids via #[Depends] - see NoDependsWithDataProviderRule.
     */
    private static IdsCollection $indexedIds;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->customFieldService = static::getContainer()->get(CustomFieldService::class);

        if (!isset(self::$indexedIds)) {
            self::$indexedIds = $this->buildIndex();
        }
    }

    protected function tearDown(): void
    {
        $this->customFieldService->reset();
    }

    #[BeforeClass]
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->beginTransaction();
    }

    #[AfterClass]
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
    }

    public function testAndSearch(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Aerodynamic Leather');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());
        $resultIds = $result->getIds();

        static::assertCount(3, $resultIds, 'But got ' . $ids->getKeys($resultIds));

        static::assertSame(
            [
                $ids->get('product-1'),
                $ids->get('product-2'),
                $ids->get('product-3'),
            ],
            $resultIds
        );
    }

    public function testOrSearch(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(false, ['name']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Aerodynamic Leather');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        $resultIds = $result->getIds();

        static::assertCount(4, $resultIds, 'But got ' . $ids->getKeys($resultIds));

        static::assertSame(
            [
                $ids->get('product-1'),
                $ids->get('product-2'),
                $ids->get('product-3'),
                $ids->get('product-4'),
            ],
            $resultIds
        );
    }

    /**
     * @param list<string> $config
     * @param list<string> $expectedProducts
     */
    #[DataProvider('providerSearchCases')]
    public function testSearch(array $config, string $term, array $expectedProducts): void
    {
        $ids = self::$indexedIds;

        $this->registerCustomFieldsMapping();
        $this->setSearchConfiguration(false, $config);
        $this->setSearchScores([]);

        // Reduce the possible products to only those, which are set up in this test class. This makes sure other tests do not interfere.
        $criteria = new Criteria(array_values($ids->all()));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        $resultIds = $result->getIds();

        static::assertCount(\count($expectedProducts), $resultIds, \sprintf('Product count mismatch, Got "%s"', $ids->getKeys($resultIds)));

        foreach ($expectedProducts as $key => $expectedProduct) {
            static::assertSame(
                $ids->get($expectedProduct),
                $resultIds[$key],
                \sprintf('Expected product %s at position %d to be there, but got "%s"', $expectedProduct, $key, (string) $ids->getKey($resultIds[$key]))
            );
        }
    }

    public function testSearchWithStopWord(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(false, ['name', 'description']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('the');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        $resultIds = $result->getIds();

        static::assertCount(0, $resultIds, 'Product count mismatch, Got ' . $ids->getKeys($resultIds));
    }

    public function testScoring(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(false, ['name', 'description', 'customSearchKeywords']);
        $this->setSearchScores(['name' => 0, 'description' => 0, 'customSearchKeywords' => 50]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $criteria->setTerm('Pokemon Raichu');

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(2, $result->getIds());

        static::assertSame(
            [
                $ids->get('product-9'), // Has Raichu as customSearchKeywords and is ranked higher
                $ids->get('product-8'), // Has Pokemon in description
            ],
            $result->getIds()
        );
    }

    /**
     * @return \Generator<string, array{list<string>, string, list<string>}>
     */
    public static function providerSearchCases(): \Generator
    {
        yield 'search inside description' => [
            ['name', 'description'],
            'fooo',
            ['product-4'],
        ];

        yield 'search for manufacturer' => [
            ['name', 'description', 'customSearchKeywords', 'manufacturer.name'],
            'Shopware',
            ['product-4'],
        ];

        yield 'search for tags' => [
            ['name', 'description', 'customSearchKeywords', 'tags.name'],
            'Smarthome',
            ['product-5'],
        ];

        yield 'search for customSearchKeywords' => [
            ['name', 'description', 'customSearchKeywords'],
            'Blueberry Activity',
            ['product-3'],
        ];

        yield 'search for categories' => [
            ['name', 'description', 'customSearchKeywords', 'categories.name'],
            'Shoes',
            ['product-1'],
        ];

        yield 'search for options' => [
            ['name', 'description', 'customSearchKeywords', 'options.name'],
            'green',
            ['product-6-1'],
        ];

        yield 'search for property' => [
            ['name', 'description', 'customSearchKeywords', 'properties.name'],
            'Wireless',
            ['product-7'],
        ];

        yield 'search for productNumber' => [
            ['name', 'description', 'customSearchKeywords', 'productNumber'],
            'SW5686779889',
            ['SW5686779889'],
        ];

        yield 'search joined technical terms in name' => [
            ['name'],
            'Channel Line',
            ['product-13'],
        ];

        yield 'search technical terms in customSearchKeywords' => [
            ['customSearchKeywords'],
            'Channel Line',
            ['product-14'],
        ];

        yield 'search productNumber without punctuation' => [
            ['productNumber'],
            'Gr49',
            ['product-12'],
        ];

        yield 'search for custom field json' => [
            ['customFields.evolvesTo'],
            'Flareon',
            ['product-10'],
        ];

        yield 'search for custom field text' => [
            ['customFields.evolvesText'],
            'Jolteon',
            ['product-11'],
        ];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    private function buildIndex(): IdsCollection
    {
        $this->connection->executeStatement('DELETE FROM product');

        static::getContainer()->get(AbstractKeyValueStorage::class)->set(ElasticsearchOptimizeSwitch::FLAG, true);

        $this->clearElasticsearch();
        $this->registerCustomFieldsMapping();
        $this->indexElasticSearch();

        $ids = new IdsCollection();
        $this->createData($ids);

        $this->refreshIndex();

        return $ids;
    }

    private function createData(IdsCollection $ids): void
    {
        $products = [
            (new ProductBuilder($ids, 'product-1'))
                ->name('Aerodynamic Leather DotCondom')
                ->tax('t1')
                ->price(50, 50)
                ->category('Shoes')
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-2'))
                ->name('Aerodynamic Leather Portaline')
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-3'))
                ->name('Aerodynamic Leather Wordlobster')
                ->price(50, 50)
                ->add('customSearchKeywords', ['Activity'])
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-4'))
                ->name('Leather Red')
                ->add('description', 'Aerodynamic Fooo')
                ->manufacturer('Shopware')
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-5'))
                ->name('Cycle Suave')
                ->price(50, 50)
                ->tag('Smarthome')
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-6'))
                ->name('T-Shirt')
                ->price(50, 50)
                ->variant(
                    (new ProductBuilder($ids, 'product-6-1'))
                        ->option('green', 'color')
                        ->build()
                )
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-7'))
                ->name('Keyboard')
                ->price(50, 50)
                ->property('Wireless', 'Connectivity')
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'SW5686779889'))
                ->name('SW Product')
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-8'))
                ->name('Super cool Pikachu Pokemon')
                ->add('description', 'A cool pokemon is traveling around the world')
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-9'))
                ->name('Super Pokemon')
                ->add('description', 'A cool raichu is traveling around the world')
                ->add('customSearchKeywords', ['Raichu'])
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-12'))
                ->name('Technical product')
                ->number('Gr.49')
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-13'))
                ->name('ChannelLine Connector')
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-14'))
                ->name('Technical keyword accessory')
                ->add('customSearchKeywords', ['ChannelLine'])
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-10'))
                ->name('Eevee')
                ->customField('evolvesTo', ['Vaporeon', 'Jolteon', 'Flareon'])
                ->price(50, 50)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'product-11'))
                ->name('EeveeCfText')
                ->customField('evolvesText', 'Jolteon')
                ->price(50, 50)
                ->visibility()
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());
    }

    private function registerCustomFieldsMapping(): void
    {
        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->addEventListener($eventDispatcher, ElasticsearchCustomFieldsMappingEvent::class, static function (ElasticsearchCustomFieldsMappingEvent $event): void {
            $event->setMapping('evolvesTo', CustomFieldTypes::SELECT);
            $event->setMapping('evolvesText', CustomFieldTypes::TEXT);
        });

        $definition = static::getContainer()->get(ElasticsearchIndexingUtils::class);

        $class = new \ReflectionClass($definition);
        $class->getProperty('customFieldsTypes')->setValue($definition, []);

        $service = new \ReflectionClass($this->customFieldService);
        $service->getProperty('customFields')->setValue($this->customFieldService, [
            'evolvesTo' => CustomFieldTypes::SELECT,
            'evolvesText' => CustomFieldTypes::TEXT,
        ]);
    }
}
