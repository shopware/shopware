<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Product;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ProductSearchQueryBuilder::class)]
class ProductSearchQueryBuilderTest extends TestCase
{
    use CacheTestBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use SessionTestBehaviour;

    private EntityRepository $productRepository;

    private Connection $connection;

    private CustomFieldService $customFieldService;

    protected function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->customFieldService = $this->getContainer()->get(CustomFieldService::class);
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

    public function testIndexing(): IdsCollection
    {
        static::expectNotToPerformAssertions();

        $this->connection->executeStatement('DELETE FROM product');

        $this->clearElasticsearch();
        $this->registerCustomFieldsMapping();
        $this->indexElasticSearch();

        $ids = new TestDataCollection();
        $this->createData($ids);

        $this->refreshIndex();

        return $ids;
    }

    #[Depends('testIndexing')]
    public function testAndSearch(IdsCollection $ids): void
    {
        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Aerodynamic Leather');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        /** @var string[] $resultIds */
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

    #[Depends('testIndexing')]
    public function testOrSearch(IdsCollection $ids): void
    {
        $this->setSearchConfiguration(false, ['name']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Aerodynamic Leather');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        /** @var string[] $resultIds */
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
     * @param array<string> $config
     * @param array<string> $expectedProducts
     */
    #[Depends('testIndexing')]
    #[DataProvider('providerSearchCases')]
    public function testSearch(array $config, string $term, array $expectedProducts, IdsCollection $ids): void
    {
        $this->registerCustomFieldsMapping();
        $this->setSearchConfiguration(false, $config);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        /** @var array<string> $resultIds */
        $resultIds = $result->getIds();

        static::assertCount(\count($expectedProducts), $resultIds, 'Product count mismatch, Got ' . $ids->getKeys($resultIds));

        foreach ($expectedProducts as $key => $expectedProduct) {
            static::assertEquals($ids->get($expectedProduct), $resultIds[$key], \sprintf('Expected product %s at position %d to be there, but got %s', $expectedProduct, $key, $ids->getKey($resultIds[$key])));
        }
    }

    #[Depends('testIndexing')]
    public function testSearchWithStopWord(IdsCollection $ids): void
    {
        $this->setSearchConfiguration(false, ['name', 'description']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('the');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->productRepository->searchIds($criteria, Context::createDefaultContext());

        /** @var array<string> $resultIds */
        $resultIds = $result->getIds();

        static::assertCount(0, $resultIds, 'Product count mismatch, Got ' . $ids->getKeys($resultIds));
    }

    #[Depends('testIndexing')]
    public function testScoring(IdsCollection $ids): void
    {
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
     * @return iterable<string, array{array<string>, string, array<string>}>
     */
    public static function providerSearchCases(): iterable
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
            'SW568',
            ['SW5686779889'],
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
        return $this->getContainer();
    }

    /**
     * @param array<string> $enabledFields
     */
    private function setSearchConfiguration(bool $andLogic = true, array $enabledFields = ['name']): void
    {
        $con = $this->connection;

        // Toggle and logic
        $con->executeStatement('UPDATE product_search_config SET and_logic = ? WHERE language_id = ?', [(int) $andLogic, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);

        $configId = $con->fetchOne('SELECT id FROM product_search_config WHERE language_id = ?', [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]);

        $con->executeStatement('DELETE FROM product_search_config_field WHERE product_search_config_id = ? AND field LIKE "customFields%"', [$configId]);

        $con->executeStatement('UPDATE product_search_config_field SET searchable = 0 WHERE product_search_config_id = ?', [$configId]);

        $con->executeStatement(
            'UPDATE product_search_config_field SET searchable = 1 WHERE product_search_config_id = :configId and field in (:fields)',
            [
                'configId' => $configId,
                'fields' => $enabledFields,
            ],
            [
                'fields' => ArrayParameterType::STRING,
            ]
        );

        foreach ($enabledFields as $enabledField) {
            if (str_contains($enabledField, 'customFields')) {
                $con->insert(
                    'product_search_config_field',
                    [
                        'id' => Uuid::randomBytes(),
                        'product_search_config_id' => $configId,
                        'field' => $enabledField,
                        'searchable' => 1,
                        'tokenize' => 0,
                        'ranking' => 0,
                        'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    ]
                );
            }
        }
    }

    /**
     * @param array<string, int> $fields
     */
    private function setSearchScores(array $fields): void
    {
        // Reset all scores
        $this->connection->executeStatement(
            'UPDATE product_search_config_field SET ranking = 0 WHERE product_search_config_id = (SELECT id FROM product_search_config WHERE language_id = ?)',
            [Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );

        foreach ($fields as $field => $value) {
            $this->connection->executeStatement(
                'UPDATE product_search_config_field SET ranking = ? WHERE product_search_config_id = (SELECT id FROM product_search_config WHERE language_id = ?) and field = ?',
                [$value, Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM), $field]
            );
        }
    }

    private function createData(TestDataCollection $ids): void
    {
        $products = [
            (new ProductBuilder($ids, 'product-1'))
                ->name('Aerodynamic Leather DotCondom')
                ->tax('t1')
                ->price(50, 50)
                ->category('Shoes')
                ->build(),
            (new ProductBuilder($ids, 'product-2'))
                ->name('Aerodynamic Leather Portaline')
                ->price(50, 50)
                ->build(),
            (new ProductBuilder($ids, 'product-3'))
                ->name('Aerodynamic Leather Wordlobster')
                ->price(50, 50)
                ->add('customSearchKeywords', ['Activity'])
                ->build(),
            (new ProductBuilder($ids, 'product-4'))
                ->name('Leather Red')
                ->add('description', 'Aerodynamic Fooo')
                ->manufacturer('Shopware')
                ->price(50, 50)
                ->build(),
            (new ProductBuilder($ids, 'product-5'))
                ->name('Cycle Suave')
                ->price(50, 50)
                ->tag('Smarthome')
                ->build(),
            (new ProductBuilder($ids, 'product-6'))
                ->name('T-Shirt')
                ->price(50, 50)
                ->variant(
                    (new ProductBuilder($ids, 'product-6-1'))
                        ->option('green', 'color')
                        ->build()
                )
                ->build(),
            (new ProductBuilder($ids, 'product-7'))
                ->name('Keyboard')
                ->price(50, 50)
                ->property('Wireless', 'Connectivity')
                ->build(),
            (new ProductBuilder($ids, 'SW5686779889'))
                ->name('SW Product')
                ->price(50, 50)
                ->build(),
            (new ProductBuilder($ids, 'product-8'))
                ->name('Super cool Pikachu Pokemon')
                ->add('description', 'A cool pokemon is traveling around the world')
                ->price(50, 50)
                ->build(),
            (new ProductBuilder($ids, 'product-9'))
                ->name('Super Pokemon')
                ->add('description', 'A cool raichu is traveling around the world')
                ->add('customSearchKeywords', ['Raichu'])
                ->price(50, 50)
                ->build(),
            (new ProductBuilder($ids, 'product-10'))
                ->name('Eevee')
                ->customField('evolvesTo', ['Vaporeon', 'Jolteon', 'Flareon'])
                ->price(50, 50)
                ->build(),
            (new ProductBuilder($ids, 'product-11'))
                ->name('EeveeCfText')
                ->customField('evolvesText', 'Jolteon')
                ->price(50, 50)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());
    }

    private function registerCustomFieldsMapping(): void
    {
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');

        $this->addEventListener($eventDispatcher, ElasticsearchCustomFieldsMappingEvent::class, function (ElasticsearchCustomFieldsMappingEvent $event): void {
            $event->setMapping('evolvesTo', CustomFieldTypes::SELECT);
            $event->setMapping('evolvesText', CustomFieldTypes::TEXT);
        });

        $definition = $this->getContainer()->get(ElasticsearchIndexingUtils::class);
        $class = new \ReflectionClass($definition);
        $reflectionProperty = $class->getProperty('customFieldsTypes');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($definition, []);

        $service = new \ReflectionClass($this->customFieldService);
        $reflectionProperty = $service->getProperty('customFields');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->customFieldService, [
            'evolvesTo' => CustomFieldTypes::SELECT,
            'evolvesText' => CustomFieldTypes::TEXT,
        ]);
    }
}
