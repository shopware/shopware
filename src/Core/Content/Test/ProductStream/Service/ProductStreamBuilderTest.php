<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ProductStream\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('business-ops')]
class ProductStreamBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $productStreamRepository;

    /**
     * @var SalesChannelRepository
     */
    private $productRepository;

    private Context $context;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var ProductStreamBuilderInterface
     */
    private $service;

    protected function setUp(): void
    {
        $this->productStreamRepository = $this->getContainer()->get('product_stream.repository');
        $this->context = Context::createDefaultContext();
        $this->service = $this->getContainer()->get(ProductStreamBuilder::class);
        $this->productRepository = $this->getContainer()->get('sales_channel.product.repository');

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testBuildFilters(): void
    {
        $this->createTestEntity();

        $products = $this->getProducts('137b079935714281ba80b40f83f8d7eb');

        static::assertCount(2, $products);
    }

    public function testNestedFilters(): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test2',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'position' => 0,
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'position' => 0,
                            'queries' => [
                                [
                                    'type' => 'contains',
                                    'field' => 'name',
                                    'value' => 'Awesome',
                                    'position' => 0,
                                ],
                                [
                                    'type' => 'not',
                                    'position' => 1,
                                    'queries' => [
                                        [
                                            'type' => 'contains',
                                            'field' => 'name',
                                            'value' => 'Copper',
                                            'position' => 0,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('product_stream.repository')
            ->create([$stream], Context::createDefaultContext());

        $filters = $this->getContainer()->get(ProductStreamBuilder::class)
            ->buildFilters($ids->get('stream'), Context::createDefaultContext());

        $expected = new MultiFilter(MultiFilter::CONNECTION_OR, [
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new ContainsFilter('product.name', 'Awesome'),
                new NotFilter(MultiFilter::CONNECTION_AND, [
                    new ContainsFilter('product.name', 'Copper'),
                ]),
            ]),
        ]);

        $filter = array_shift($filters);
        static::assertInstanceOf(MultiFilter::class, $filter);

        static::assertEquals($expected, $filter);
    }

    public function testNoFilters(): void
    {
        $this->createTestEntityWithoutFilters();

        static::expectException(NoFilterException::class);

        $this->getProducts('137b079935714281ba80b40f83f8d7eb');
    }

    /**
     * @dataProvider relativeTimeFiltersDataProvider
     */
    public function testRelativeTimeFilters(string $type, string $operator, string $field, string $value, array $releaseDates, int $expected): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream2'),
            'name' => 'test3',
            'filters' => [
                [
                    'type' => $type,
                    'field' => $field,
                    'value' => $value,
                    'parameters' => [
                        'operator' => $operator,
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('product_stream.repository')
            ->create([$stream], Context::createDefaultContext());

        $this->createProducts($releaseDates);

        $products = $this->getProducts($ids->get('stream2'));

        static::assertCount($expected, $products);
    }

    public static function relativeTimeFiltersDataProvider(): array
    {
        return [
            'days until - gt' => ['until', 'gt', 'releaseDate', 'P5D', self::getReleaseDates('+'), 3],
            'days until - lt' => ['until', 'lt', 'releaseDate', 'P5D', self::getReleaseDates('+'), 5],
            'days until - gte' => ['until', 'gte', 'releaseDate', 'P5D', self::getReleaseDates('+'), 5],
            'days until - lte' => ['until', 'lte', 'releaseDate', 'P5D', self::getReleaseDates('+'), 7],
            'days until - eq' => ['until', 'eq', 'releaseDate', 'P5D', self::getReleaseDates('+'), 2],
            'days until - neq' => ['until', 'neq', 'releaseDate', 'P5D', self::getReleaseDates('+'), 8],
            'days since - gt' => ['since', 'gt', 'releaseDate', 'P5D', self::getReleaseDates('-'), 3],
            'days since - lt' => ['since', 'lt', 'releaseDate', 'P5D', self::getReleaseDates('-'), 5],
            'days since - gte' => ['since', 'gte', 'releaseDate', 'P5D', self::getReleaseDates('-'), 5],
            'days since - lte' => ['since', 'lte', 'releaseDate', 'P5D', self::getReleaseDates('-'), 7],
            'days since - eq' => ['since', 'eq', 'releaseDate', 'P5D', self::getReleaseDates('-'), 2],
            'days since - neq' => ['since', 'neq', 'releaseDate', 'P5D', self::getReleaseDates('-'), 8],
        ];
    }

    private function getProducts(string $productStreamId): EntitySearchResult
    {
        $filters = $this->service->buildFilters($productStreamId, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(...$filters);

        return $this->productRepository->search($criteria, $this->salesChannelContext);
    }

    private function createTestEntity(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $randomProductIds = implode('|', \array_slice(array_column($this->createProducts(), 'id'), 0, 2));

        $connection->executeStatement(
            "
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"product.id\", \"value\": \"{$randomProductIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"product.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"product.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"product.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
        "
        );

        $connection->executeStatement(
            "
            INSERT INTO `product_stream_filter` (`id`, `product_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                (UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 3, NULL, '2019-08-16 08:43:57.486', NULL),
                (UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.470', NULL),
                (UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 2, NULL, '2019-08-16 08:43:57.483', NULL),
                (UNHEX('56C5DF0B41954334A7B0CDFEDFE1D7E9'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), 'range', 'width', NULL, NULL, '{\"lte\":932,\"gte\":221}', 1, NULL, '2019-08-16 08:43:57.488', NULL),
                (UNHEX('6382E03A768F444E9C2A809C63102BD4'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), 'range', 'height', NULL, NULL, '{\"gte\":182}', 2, NULL, '2019-08-16 08:43:57.485', NULL),
                (UNHEX('7CBC1236ABCD43CAA697E9600BF1DF6E'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), 'range', 'width', NULL, NULL, '{\"lte\":245}', 1, NULL, '2019-08-16 08:43:57.476', NULL),
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomProductIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    "
        );
    }

    private function createTestEntityWithoutFilters(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $connection->executeStatement(
            '
            INSERT INTO `product_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), \'[]\', 0, \'2019-08-16 08:43:57.488\', NULL);
        '
        );
    }

    private function createProducts(?array $releaseDates = null): array
    {
        $productRepository = $this->getContainer()->get('product.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $salesChannelId = TestDefaults::SALES_CHANNEL;
        $products = [];

        for ($i = 0; $i < 10; ++$i) {
            $products[] = [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
                'releaseDate' => $releaseDates ? $releaseDates[$i] : null,
            ];
        }

        $productRepository->create($products, $this->context);
        $this->addTaxDataToSalesChannel($this->salesChannelContext, end($products)['tax']);

        return $products;
    }

    private static function getReleaseDates(string $operator): array
    {
        return [
            (new \DateTimeImmutable())->modify($operator . '8 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '5 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '9 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '12 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '4 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '3 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '5 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '1 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '2 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '3 days')->format('Y-m-d'),
        ];
    }
}
