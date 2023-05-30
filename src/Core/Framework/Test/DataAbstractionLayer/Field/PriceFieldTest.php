<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\PriceFieldDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @internal
 */
class PriceFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    private static string $otherCurrencyId = '0fa91ce3e96a4bc2be4bd9ce752c3425';

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _test_nullable;
CREATE TABLE `_test_nullable` (
  `id` varbinary(16) NOT NULL,
  `data` longtext NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->rollBack();
        $this->connection->executeStatement($nullableTable);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `_test_nullable`');
        $this->connection->beginTransaction();
    }

    public function testListPriceLoading(): void
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        $ids = new TestDataCollection();

        $data = [
            [
                'id' => $ids->create('with-was'),
                'data' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 1,
                        'net' => 1,
                        'linked' => false,
                        'listPrice' => [
                            'gross' => 2,
                            'net' => 2,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];

        $definition = $this->registerDefinition(PriceFieldDefinition::class);
        $this->getContainer()
            ->get(EntityWriter::class)
            ->insert($definition, $data, $context);

        $entity = $this->getContainer()->get(EntityReaderInterface::class)
            ->read($definition, new Criteria([$ids->get('with-was')]), Context::createDefaultContext())
            ->get($ids->get('with-was'));

        $price = $entity->get('data');

        /** @var PriceCollection $price */
        static::assertInstanceOf(PriceCollection::class, $price);

        $price = $price->getCurrencyPrice(Defaults::CURRENCY);
        /** @var Price $price */
        static::assertInstanceOf(Price::class, $price);

        static::assertInstanceOf(Price::class, $price->getListPrice());
        static::assertEquals(2, $price->getListPrice()->getNet());
    }

    public function testListPriceInCriteriaParts(): void
    {
        $context = WriteContext::createFromContext(Context::createDefaultContext());

        $ids = new TestDataCollection();

        $data = [
            [
                'id' => $ids->create('was'),
                'data' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 1,
                        'net' => 1,
                        'linked' => false,
                        'listPrice' => [
                            'gross' => 2,
                            'net' => 2,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
            [
                'id' => $ids->create('was-2'),
                'data' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 1,
                        'net' => 1,
                        'linked' => false,
                        'listPrice' => [
                            'gross' => 10,
                            'net' => 10,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];

        $definition = $this->registerDefinition(PriceFieldDefinition::class);
        $this->getContainer()
            ->get(EntityWriter::class)
            ->insert($definition, $data, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('data.listPrice', 2));

        $result = $this->getContainer()
            ->get(EntitySearcherInterface::class)
            ->search($definition, $criteria, Context::createDefaultContext());

        static::assertCount(1, $result->getIds());
        static::assertTrue($result->has($ids->get('was')));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('data.listPrice', 3));

        $result = $this->getContainer()
            ->get(EntitySearcherInterface::class)
            ->search($definition, $criteria, Context::createDefaultContext());

        static::assertCount(0, $result->getIds());

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data.listPrice', FieldSorting::DESCENDING));

        $result = $this->getContainer()
            ->get(EntitySearcherInterface::class)
            ->search($definition, $criteria, Context::createDefaultContext());

        static::assertEquals(
            [
                $ids->get('was-2'),
                $ids->get('was'),
            ],
            $result->getIds()
        );
    }

    public static function cashRoundingSortingProvider()
    {
        $ids = new TestDataCollection();

        return [
            '0.01 interval default currency' => [
                [
                    ['id' => $ids->create('record-1'), 'data' => [self::gross(19.992)]],
                    ['id' => $ids->create('record-2'), 'data' => [self::gross(19.995)]],
                ],
                [$ids->get('record-1'), $ids->get('record-2')],
                new CashRoundingConfig(2, 0.01, true),
            ],
            '0.01 interval currency fallback' => [
                [
                    ['id' => $ids->create('record-1'), 'data' => [self::gross(19), self::gross(8, self::$otherCurrencyId)]],
                    ['id' => $ids->create('record-2'), 'data' => [self::gross(6)]], // factor 1.5 => 9 €
                ],
                [$ids->get('record-1'), $ids->get('record-2')],
                new CashRoundingConfig(2, 0.01, true),
                self::$otherCurrencyId,
            ],

            '0.05 interval default currency' => [
                [
                    ['id' => $ids->create('record-1'), 'data' => [self::gross(19.04)]],    // round to 19.00
                    ['id' => $ids->create('record-2'), 'data' => [self::gross(19.01)]],    // round to 19.05
                    ['id' => $ids->create('record-3'), 'data' => [self::gross(19.08)]],     // round to 19.10
                ],
                [$ids->get('record-2'), $ids->get('record-1'), $ids->get('record-3')],
                new CashRoundingConfig(2, 0.05, true),
                self::$otherCurrencyId,
            ],
            '0.05 interval currency fallback' => [
                [
                    ['id' => $ids->create('record-1'), 'data' => [self::gross(19.04)]],                                                // 19.05 * 1.5 = 28.575 ~ 28.60
                    ['id' => $ids->create('record-2'), 'data' => [self::gross(19.01)]],                                                // 19.01 * 1.5 = 28.515 ~ 28.50
                    ['id' => $ids->create('record-3'), 'data' => [self::gross(19.08), self::gross(28.55, self::$otherCurrencyId)]],     // ~ 28.55
                ],
                [$ids->get('record-2'), $ids->get('record-3'), $ids->get('record-1')],
                new CashRoundingConfig(2, 0.05, true),
                self::$otherCurrencyId,
            ],
        ];
    }

    /**
     * @dataProvider cashRoundingSortingProvider
     */
    public function testCashRoundingSorting(
        array $records,
        array $expected,
        CashRoundingConfig $rounding,
        string $currencyId = Defaults::CURRENCY
    ): void {
        $definition = $this->registerDefinition(PriceFieldDefinition::class);

        $currency = [
            'id' => self::$otherCurrencyId,
            'name' => 'test',
            'factor' => 1.5,
            'symbol' => 'A',
            'shortName' => 'A',
            'isoCode' => 'A',
            'itemRounding' => json_decode(json_encode($rounding, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode($rounding, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        ];

        $this->getContainer()
            ->get('currency.repository')
            ->upsert([$currency], Context::createDefaultContext());

        $ids = new TestDataCollection();

        $this->getContainer()
            ->get(EntityWriter::class)
            ->insert($definition, $records, WriteContext::createFromContext(Context::createDefaultContext()));

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data'));

        // other currency provided? switch factor to 1.5 of above currency
        $factor = 1.0;
        if ($currencyId === self::$otherCurrencyId) {
            $factor = 1.5;
        }

        $context = Context::createDefaultContext();
        $context->assign([
            'itemRounding' => $rounding,
            'currencyId' => $currencyId,
            'currencyFactor' => $factor,
        ]);

        // test ascending sorting
        $result = $this->getContainer()
            ->get(EntitySearcherInterface::class)
            ->search($definition, $criteria, $context);

        static::assertEquals($expected, array_values($result->getIds()));

        // test descending sorting
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('data', FieldSorting::DESCENDING));

        $result = $this->getContainer()
            ->get(EntitySearcherInterface::class)
            ->search($definition, $criteria, $context);

        static::assertEquals(array_reverse($expected), array_values($result->getIds()));
    }

    public static function cashRoundingFilterProvider()
    {
        $ids = new TestDataCollection();

        return [
            '0.01 interval default currency' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.99, RangeFilter::LTE => 20.05]),
                [
                    ['id' => $ids->create('record-1'), 'data' => [self::gross(19.992)]],    // ~ 19.99
                    ['id' => $ids->create('record-2'), 'data' => [self::gross(19.995)]],    // ~ 20.00
                    ['id' => $ids->create('record-3'), 'data' => [self::gross(20.055)]],    // ~ 20.06
                ],
                [$ids->get('record-1'), $ids->get('record-2')],
                new CashRoundingConfig(2, 0.01, true),
            ],
            '0.01 interval currency fallback' => [
                new RangeFilter('data', [RangeFilter::GTE => 29.91, RangeFilter::LTE => 30.08]),
                [
                    ['id' => $ids->create('record-1'), 'data' => [self::gross(19), self::gross(29.90, self::$otherCurrencyId)]],
                    ['id' => $ids->create('record-2'), 'data' => [self::gross(19.99)]],   // 19.99 * 1.5 = 29.985 ~ 29.99
                    ['id' => $ids->create('record-3'), 'data' => [self::gross(20.055)]],  // 20.055 * 1.5 = 30.0825 ~ 30.08
                ],
                [$ids->get('record-3'), $ids->get('record-2')],
                new CashRoundingConfig(2, 0.01, true),
                self::$otherCurrencyId,
            ],

            '0.05 interval default currency' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.51, RangeFilter::LTE => 19.55]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.50)]],  // ~ 19.50
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.51)]],  // ~ 19.50
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.52)]],  // ~ 19.50
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.524)]], // ~ 19.50
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.525)]], // ~ 19.55
                    ['id' => $ids->create('r-6'), 'data' => [self::gross(19.53)]],  // ~ 19.55
                    ['id' => $ids->create('r-7'), 'data' => [self::gross(19.54)]],  // ~ 19.55
                    ['id' => $ids->create('r-8'), 'data' => [self::gross(19.55)]],  // ~ 19.55
                    ['id' => $ids->create('r-9'), 'data' => [self::gross(19.56)]],  // ~ 19.55
                    ['id' => $ids->create('r-10'), 'data' => [self::gross(19.57)]],  // ~ 19.55
                    ['id' => $ids->create('r-11'), 'data' => [self::gross(19.574)]], // ~ 19.55
                    ['id' => $ids->create('r-12'), 'data' => [self::gross(19.575)]], // ~ 19.60
                    ['id' => $ids->create('r-13'), 'data' => [self::gross(19.58)]],  // ~ 19.60
                    ['id' => $ids->create('r-14'), 'data' => [self::gross(19.59)]],  // ~ 19.60
                ],
                $ids->getList(['r-5', 'r-6', 'r-7', 'r-8', 'r-9', 'r-10', 'r-11']),
                new CashRoundingConfig(2, 0.05, true),
            ],

            '0.05 interval currency fallback' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.51, RangeFilter::LTE => 19.55]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.50 / 1.5)]],  // ~ 19.50
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.51 / 1.5)]],  // ~ 19.50
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.52 / 1.5)]],  // ~ 19.50
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.524 / 1.5)]], // ~ 19.50
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.525 / 1.5)]], // ~ 19.55
                    ['id' => $ids->create('r-6'), 'data' => [self::gross(19.53 / 1.5)]],  // ~ 19.55
                    ['id' => $ids->create('r-7'), 'data' => [self::gross(19.54 / 1.5)]],  // ~ 19.55
                    ['id' => $ids->create('r-8'), 'data' => [self::gross(19.55 / 1.5)]],  // ~ 19.55
                    ['id' => $ids->create('r-9'), 'data' => [self::gross(19.56 / 1.5)]],  // ~ 19.55
                    ['id' => $ids->create('r-10'), 'data' => [self::gross(19.57 / 1.5)]],  // ~ 19.55
                    ['id' => $ids->create('r-11'), 'data' => [self::gross(19.574 / 1.5)]], // ~ 19.55
                    ['id' => $ids->create('r-12'), 'data' => [self::gross(19.575 / 1.5)]], // ~ 19.60
                    ['id' => $ids->create('r-13'), 'data' => [self::gross(19.58 / 1.5)]],  // ~ 19.60
                    ['id' => $ids->create('r-14'), 'data' => [self::gross(19.59 / 1.5)]],  // ~ 19.60
                ],
                $ids->getList(['r-5', 'r-6', 'r-7', 'r-8', 'r-9', 'r-10', 'r-11']),
                new CashRoundingConfig(2, 0.05, true),
                self::$otherCurrencyId,
            ],

            '0.10 interval default currency' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.41, RangeFilter::LTE => 19.50]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.40)]],   // ~19.40
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.41)]],   // ~19.40
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.44)]],   // ~19.40
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.45)]],   // ~19.50
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.49)]],   // ~19.50
                ],
                $ids->getList(['r-4', 'r-5']),
                new CashRoundingConfig(2, 0.10, true),
            ],

            '0.10 interval currency fallback' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.41, RangeFilter::LTE => 19.50]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.40 / 1.5)]],   // ~19.40
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.41 / 1.5)]],   // ~19.40
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.44 / 1.5)]],   // ~19.40
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.45 / 1.5)]],   // ~19.50
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.49 / 1.5)]],   // ~19.50
                ],
                $ids->getList(['r-4', 'r-5']),
                new CashRoundingConfig(2, 0.10, true),
                self::$otherCurrencyId,
            ],

            '0.50 interval default currency' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.01, RangeFilter::LTE => 20.00]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.01)]],     // ~19.00
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.24)]],     // ~19.00
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.25)]],     // ~19.50
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.49)]],     // ~19.50
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.50)]],     // ~19.50
                    ['id' => $ids->create('r-6'), 'data' => [self::gross(19.51)]],     // ~19.50
                    ['id' => $ids->create('r-7'), 'data' => [self::gross(19.74)]],     // ~19.50
                    ['id' => $ids->create('r-8'), 'data' => [self::gross(19.75)]],     // ~20.00
                    ['id' => $ids->create('r-9'), 'data' => [self::gross(19.99)]],     // ~20.00
                ],
                $ids->getList(['r-3', 'r-4', 'r-5', 'r-6', 'r-7', 'r-8', 'r-9']),
                new CashRoundingConfig(2, 0.50, true),
            ],

            '0.50 interval currency fallback' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.01, RangeFilter::LTE => 20.00]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.01 / 1.5)]],     // ~19.00
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.24 / 1.5)]],     // ~19.00
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.25 / 1.5)]],     // ~19.50
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.49 / 1.5)]],     // ~19.50
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.50 / 1.5)]],     // ~19.50
                    ['id' => $ids->create('r-6'), 'data' => [self::gross(19.51 / 1.5)]],     // ~19.50
                    ['id' => $ids->create('r-7'), 'data' => [self::gross(19.74 / 1.5)]],     // ~19.50
                    ['id' => $ids->create('r-8'), 'data' => [self::gross(19.75 / 1.5)]],     // ~20.00
                    ['id' => $ids->create('r-9'), 'data' => [self::gross(19.99 / 1.5)]],     // ~20.00
                ],
                $ids->getList(['r-3', 'r-4', 'r-5', 'r-6', 'r-7', 'r-8', 'r-9']),
                new CashRoundingConfig(2, 0.50, true),
                self::$otherCurrencyId,
            ],

            '1.00 interval default currency' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.01, RangeFilter::LTE => 20.00]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.00)]],   // ~19.00
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.01)]],   // ~19.00
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.49)]],   // ~19.00
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.50)]],   // ~20.00
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.99)]],   // ~20.00
                ],
                $ids->getList(['r-4', 'r-5']),
                new CashRoundingConfig(2, 1.00, true),
            ],

            '1.00 interval currency fallback' => [
                new RangeFilter('data', [RangeFilter::GTE => 19.01, RangeFilter::LTE => 20.00]),
                [
                    ['id' => $ids->create('r-1'), 'data' => [self::gross(19.00 / 1.5)]],   // ~19.00
                    ['id' => $ids->create('r-2'), 'data' => [self::gross(19.01 / 1.5)]],   // ~19.00
                    ['id' => $ids->create('r-3'), 'data' => [self::gross(19.49 / 1.5)]],   // ~19.00
                    ['id' => $ids->create('r-4'), 'data' => [self::gross(19.50 / 1.5)]],   // ~20.00
                    ['id' => $ids->create('r-5'), 'data' => [self::gross(19.99 / 1.5)]],   // ~20.00
                ],
                $ids->getList(['r-4', 'r-5']),
                new CashRoundingConfig(2, 1.00, true),
                self::$otherCurrencyId,
            ],
        ];
    }

    /**
     * @dataProvider cashRoundingFilterProvider
     */
    public function testCashRoundingFilter(
        RangeFilter $filter,
        array $records,
        array $expected,
        CashRoundingConfig $rounding,
        string $currencyId = Defaults::CURRENCY
    ): void {
        $definition = $this->registerDefinition(PriceFieldDefinition::class);

        $currency = [
            'id' => self::$otherCurrencyId,
            'name' => 'test',
            'factor' => 1.5,
            'symbol' => 'A',
            'shortName' => 'A',
            'isoCode' => 'A',
            'itemRounding' => json_decode(json_encode($rounding, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode($rounding, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
        ];

        $this->getContainer()
            ->get('currency.repository')
            ->upsert([$currency], Context::createDefaultContext());

        $ids = new TestDataCollection();

        $this->getContainer()
            ->get(EntityWriter::class)
            ->insert($definition, $records, WriteContext::createFromContext(Context::createDefaultContext()));

        // other currency provided? switch factor to 1.5 of above currency
        $factor = 1.0;
        if ($currencyId === self::$otherCurrencyId) {
            $factor = 1.5;
        }

        $context = Context::createDefaultContext();
        $context->assign([
            'rounding' => $rounding,
            'currencyId' => $currencyId,
            'currencyFactor' => $factor,
        ]);

        $criteria = new Criteria(array_column($records, 'id'));
        $criteria->addFilter($filter);

        // test ascending sorting
        $result = $this->getContainer()
            ->get(EntitySearcherInterface::class)
            ->search($definition, $criteria, $context);

        static::assertEquals(\count($expected), $result->getTotal(), print_r($result->getData(), true));
        foreach ($expected as $id) {
            static::assertTrue($result->has($id));
        }
    }

    private static function gross(float $gross, string $currencyId = Defaults::CURRENCY)
    {
        return ['currencyId' => $currencyId, 'gross' => $gross, 'net' => 1, 'linked' => true];
    }
}
