<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class CheapestPriceTest extends TestCase
{
    use KernelTestBehaviour;

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

    /*
              No   | a   | b   | a+b | b+a
    p.1       70   |     |     |     |
    v.2.1     80   |     |     |     |
    v.2.2     79   |     |     |     |
    v.3.1     90   |     |     |     |
    v.3.2     100  |     |     |     |
    v.4.1     60   |     |     |     |
    v.4.2     70   |     |     |     |
    p.5       110  | 120 |     |     |
    v.6.1     120  | 130 |     |     |
    v.6.2     120  | 140 |     |     |
    v.7.1     130  | 150 |     |     |
    v.7.2     130  | 140 |     |     |
    v.8.1     140  | 160 |     |     |
    v.8.2     140  | 170 |     |     |
    v.9.1     150  | 160 |     |     |
    v.9.2     160  |     |     |     |
    v.10.1    160  | 160 |     |     |
    v.10.2    150  |     |     |     |
    v.11.1    170  | 180 | 190 | 180 | 190
    v.11.2    170  | 180 | 190 | 180 | 190
    v.12.1    180  | 210 |     | 210 | 210
    v.12.2    180  | 200 | 190 | 200 | 190
    v.13.1    190  | 220 |     | 220 | 220
    v.13.2    190  | 210 | 200 | 210 | 200
     */

    public function testIndexing(?IdsCollection $ids = null): IdsCollection
    {
        try {
            $ids ??= new IdsCollection();
            $currency = [
                'id' => $ids->get('currency'),
                'factor' => 2.0,
                'symbol' => 'T',
                'isoCode' => 'TTT',
                'position' => 3,
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'shortName' => 'TE',
                'name' => 'Test',
            ];
            static::getContainer()->get('currency.repository')
                ->create([$currency], Context::createDefaultContext());

            $products = [
                // no rule = 70€
                (new ProductBuilder($ids, 'p.1'))
                    ->price(70, null, 'default', 77)
                    ->price(99, null, 'currency')
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->build(),

                // no rule = 79€
                (new ProductBuilder($ids, 'p.2'))
                    ->active(false)
                    ->price(80)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.2.1'))
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.2.2'))
                            ->price(79)
                            ->price(88, null, 'currency')
                            ->build()
                    )
                    ->build(),

                // no rule = 90€
                (new ProductBuilder($ids, 'p.3'))
                    ->price(90)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.3.1'))
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.3.2'))
                            ->price(100)
                            ->build()
                    )
                    ->build(),

                // no rule = 60€
                (new ProductBuilder($ids, 'p.4'))
                    ->price(100)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.4.1'))
                            ->price(60)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.4.2'))
                            ->price(70, null, 'default', 77)
                            ->price(101, null, 'currency')
                            ->build()
                    )
                    ->build(),

                // no rule = 110€  ||  rule-a = 130€
                (new ProductBuilder($ids, 'p.5'))
                    ->price(110)
                    ->prices('rule-a', 130, 'default')
                    ->prices('rule-a', 120, 'default', null, 3, false, 150)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->build(),

                // no rule = 120€  ||  rule-a = 130€
                (new ProductBuilder($ids, 'p.6'))
                    ->price(120)
                    ->prices('rule-a', 150)
                    ->prices('rule-a', 140, 'default', null, 3)
                    ->prices('rule-a', 199, 'currency')
                    ->prices('rule-a', 188, 'currency', null, 3)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.6.1'))
                            ->prices('rule-a', 140)
                            ->prices('rule-a', 130, 'default', null, 3, false, 162)
                            ->prices('rule-a', 188, 'currency')
                            ->prices('rule-a', 177, 'currency', null, 3)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.6.2'))
                            ->build()
                    )
                    ->build(),

                // no rule = 130€  ||   rule-a = 150€
                (new ProductBuilder($ids, 'p.7'))
                    ->price(130)
                    ->prices('rule-a', 150)
                    ->prices('rule-a', 140, 'default', null, 3)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.7.1'))
                            ->prices('rule-a', 160)
                            ->prices('rule-a', 150, 'default', null, 3)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.7.2'))
                            ->build()
                    )
                    ->build(),

                // no rule = 140€  ||  rule-a = 170€
                (new ProductBuilder($ids, 'p.8'))
                    ->price(140)
                    ->prices('rule-a', 160)
                    ->prices('rule-a', 150, 'default', null, 3)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.8.1'))
                            ->prices('rule-a', 170)
                            ->prices('rule-a', 160, 'default', null, 3)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.8.2'))
                            ->prices('rule-a', 180)
                            ->prices('rule-a', 170, 'default', null, 3)
                            ->build()
                    )
                    ->build(),

                // no-rule = 150€   ||   rule-a  = 160€
                (new ProductBuilder($ids, 'p.9'))
                    ->price(150)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.9.1'))
                            ->prices('rule-a', 170)
                            ->prices('rule-a', 160, 'default', null, 3)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.9.2'))
                            ->price(160)
                            ->build()
                    )
                    ->build(),

                // no rule = 150€  ||  rule-a = 150€
                (new ProductBuilder($ids, 'p.10'))
                    ->price(160)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.10.1'))
                            ->prices('rule-a', 170)
                            ->prices('rule-a', 160, 'default', null, 3)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.10.2'))
                            ->price(150)
                            ->build()
                    )
                    ->build(),

                // no-rule = 170  || rule-a = 190  || rule-b = 200
                (new ProductBuilder($ids, 'p.11'))
                    ->price(170)
                    ->prices('rule-a', 190)
                    ->prices('rule-a', 180, 'default', null, 3)
                    ->prices('rule-b', 200)
                    ->prices('rule-b', 190, 'default', null, 3)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.11.1'))
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.11.2'))
                            ->build()
                    )
                    ->build(),

                // no rule = 180 ||  rule-a = 210  || rule-b = 180 || a+b = 210 || b+a = 220
                (new ProductBuilder($ids, 'p.12'))
                    ->price(180)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.12.1'))
                            ->prices('rule-a', 220)
                            ->prices('rule-a', 210, 'default', null, 3)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.12.2'))
                            ->prices('rule-a', 210)
                            ->prices('rule-a', 200, 'default', null, 3)
                            ->prices('rule-b', 200)
                            ->prices('rule-b', 190, 'default', null, 3, false, 271)
                            ->build()
                    )
                    ->build(),

                // no rule = 190 ||  rule-a = 220  || rule-b = 190 || a+b = 220 || b+a = 220
                (new ProductBuilder($ids, 'p.13'))
                    ->price(190)
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->prices('rule-a', 230)
                    ->prices('rule-a', 220, 'default', null, 3)
                    ->variant(
                        (new ProductBuilder($ids, 'v.13.1'))
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.13.2'))
                            ->prices('rule-a', 220)
                            ->prices('rule-a', 210, 'default', null, 3)
                            ->prices('rule-b', 210)
                            ->prices('rule-b', 200, 'default', null, 3, false, 285)
                            ->build()
                    )
                    ->build(),

                // even not available variants/products should be calculated correctly
                (new ProductBuilder($ids, 'p.14'))
                    ->price(180)
                    ->closeout()
                    ->visibility(TestDefaults::SALES_CHANNEL)
                    ->variant(
                        (new ProductBuilder($ids, 'v.14.1'))
                            ->stock(0)
                            ->build()
                    )
                    ->variant(
                        (new ProductBuilder($ids, 'v.14.2'))
                            ->price(181)
                            ->stock(2)
                            ->build()
                    )
                    ->build(),
            ];

            static::getContainer()->get('product.repository')
                ->create($products, Context::createDefaultContext());
            $criteria = new Criteria($ids->all());
            $result = static::getContainer()->get('product.repository')
                ->searchIds($criteria, Context::createDefaultContext());
            static::assertNotNull($result);

            return $ids;
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCalculator(IdsCollection $ids): void
    {
        try {
            $cases = $this->calculationProvider($ids);

            $default = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

            $currency = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, ['currencyId' => $ids->get('currency')]);

            $contexts = [
                Defaults::CURRENCY => $default,
                $ids->get('currency') => $currency,
            ];
            foreach ($cases as $message => $case) {
                $context = $contexts[$case['currencyId']];

                $context->setRuleIds($case['rules']);

                $assertions = $case['assertions'];

                /** @var string[] $keys */
                $keys = array_keys($assertions);

                $criteria = new Criteria($ids->getList($keys));

                $products = static::getContainer()->get('sales_channel.product.repository')
                    ->search($criteria, $context);

                foreach ($assertions as $key => $assertion) {
                    $id = $ids->get($key);

                    $product = $products->get($id);

                    $error = \sprintf('Case "%s": Product with key %s not found', $message, $key);
                    static::assertInstanceOf(SalesChannelProductEntity::class, $product, $error);

                    $error = \sprintf('Case "%s": Product with key %s, calculated price not match', $message, $key);
                    static::assertSame($assertion['price'], $product->getCalculatedPrice()->getUnitPrice(), $error);

                    $error = \sprintf('Case "%s": Product with key %s, advanced prices count not match', $message, $key);
                    static::assertCount(\count($assertion['prices']), $product->getCalculatedPrices(), $error);
                    foreach ($assertion['prices'] as $index => $expected) {
                        $price = $product->getCalculatedPrices()->get($index);

                        $error = \sprintf('Case "%s": Product with key %s, advanced prices with index %s not match', $message, $key, $index);
                        static::assertInstanceOf(CalculatedPrice::class, $price, $error);
                        static::assertSame($expected, $price->getUnitPrice(), $error);
                    }

                    $error = \sprintf('Case "%s": Product with key %s, cheapest price not match', $message, $key);
                    static::assertSame($assertion['cheapest'], $product->getCalculatedCheapestPrice()->getUnitPrice(), $error);
                }
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testCalculatorBackwardsCompatibility(IdsCollection $ids): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $cheapestPriceQuery = $connection->prepare('UPDATE product SET cheapest_price = :price WHERE id = :id AND version_id = :version');

        /** @var string $prices */
        $prices = file_get_contents(__DIR__ . '/_fixtures/serialized_prices.json');
        foreach ($ids->all() as $key => $id) {
            $prices = str_replace(\sprintf('__id_placeholder_%s__', $key), $id, $prices);
        }
        foreach (\json_decode($prices, true, 512, \JSON_THROW_ON_ERROR) as $productName => $serializedPrice) {
            StatementHelper::executeStatement($cheapestPriceQuery, [
                'price' => $serializedPrice,
                'id' => $ids->getBytes($productName),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
        }

        try {
            $cases = $this->calculationProvider($ids);

            $default = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

            $currency = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, ['currencyId' => $ids->get('currency')]);

            $contexts = [
                Defaults::CURRENCY => $default,
                $ids->get('currency') => $currency,
            ];
            foreach ($cases as $message => $case) {
                $context = $contexts[$case['currencyId']];

                $context->setRuleIds($case['rules']);

                $assertions = $case['assertions'];

                /** @var string[] $keys */
                $keys = array_keys($assertions);

                $criteria = new Criteria($ids->getList($keys));

                $products = static::getContainer()->get('sales_channel.product.repository')
                    ->search($criteria, $context);

                foreach ($assertions as $key => $assertion) {
                    $id = $ids->get($key);

                    $product = $products->get($id);

                    $error = \sprintf('Case "%s": Product with key %s not found', $message, $key);
                    static::assertInstanceOf(SalesChannelProductEntity::class, $product, $error);

                    $error = \sprintf('Case "%s": Product with key %s, calculated price not match', $message, $key);
                    static::assertSame($assertion['price'], $product->getCalculatedPrice()->getUnitPrice(), $error);

                    $error = \sprintf('Case "%s": Product with key %s, advanced prices count not match', $message, $key);
                    static::assertCount(\count($assertion['prices']), $product->getCalculatedPrices(), $error);
                    foreach ($assertion['prices'] as $index => $expected) {
                        $price = $product->getCalculatedPrices()->get($index);

                        $error = \sprintf('Case "%s": Product with key %s, advanced prices with index %s not match', $message, $key, $index);
                        static::assertInstanceOf(CalculatedPrice::class, $price, $error);
                        static::assertSame($expected, $price->getUnitPrice(), $error);
                    }

                    $error = \sprintf('Case "%s": Product with key %s, cheapest price not match', $message, $key);
                    static::assertSame($assertion['cheapest'], $product->getCalculatedCheapestPrice()->getUnitPrice(), $error);
                }
            }
        } finally {
            // Manually handle state changes
            static::stopTransactionAfter();
            static::startTransactionBefore();
            $this->testIndexing($ids);
        }
    }

    #[Depends('testIndexing')]
    public function testFilterPercentage(IdsCollection $ids): void
    {
        try {
            $cases = $this->providerFilterPercentage();

            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

            foreach ($cases as $message => $case) {
                $criteria = new Criteria(array_values($ids->all()));

                $criteria->addFilter(
                    new RangeFilter('product.cheapestPrice.percentage', [
                        RangeFilter::GTE => (float) $case['from'],
                        RangeFilter::LTE => (float) $case['to'],
                    ])
                );

                $context->setRuleIds([]);
                if (isset($case['rules'])) {
                    $context->setRuleIds($ids->getList($case['rules']));
                }

                $result = static::getContainer()->get('sales_channel.product.repository')
                    ->searchIds($criteria, $context);

                static::assertCount(\count($case['expected']), $result->getIds(), $message . ' failed');

                foreach ($case['expected'] as $key) {
                    static::assertTrue($result->has($ids->get($key)), \sprintf('Missing id %s in case `%s`', $key, $message));
                }
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testFilterPrice(IdsCollection $ids): void
    {
        try {
            $cases = $this->providerFilterPrice();

            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

            foreach ($cases as $message => $case) {
                $criteria = new Criteria(array_values($ids->all()));

                $criteria->addFilter(
                    new RangeFilter('product.cheapestPrice', [
                        RangeFilter::GTE => (float) $case['from'],
                        RangeFilter::LTE => (float) $case['to'],
                    ])
                );

                $context->setRuleIds([]);
                if (isset($case['rules'])) {
                    $context->setRuleIds($ids->getList($case['rules']));
                }

                $result = static::getContainer()->get('sales_channel.product.repository')
                    ->searchIds($criteria, $context);

                static::assertCount(\count($case['expected']), $result->getIds(), $message . ' failed');

                foreach ($case['expected'] as $key) {
                    static::assertTrue($result->has($ids->get($key)), \sprintf('Missing id %s in case `%s`', $key, $message));
                }
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    #[Depends('testIndexing')]
    public function testSorting(IdsCollection $ids): void
    {
        try {
            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

            $cases = $this->providerSorting();
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

    #[Depends('testIndexing')]
    public function testAggregation(IdsCollection $ids): void
    {
        try {
            $criteria = new Criteria(array_values($ids->all()));
            $criteria->addAggregation(new StatsAggregation('price', 'product.cheapestPrice'));

            $context = static::getContainer()->get(SalesChannelContextFactory::class)
                ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

            $cases = $this->providerAggregation();
            foreach ($cases as $message => $case) {
                $context->setRuleIds($ids->getList($case['rules']));

                $result = static::getContainer()->get('sales_channel.product.repository')
                    ->aggregate($criteria, $context);

                $aggregation = $result->get('price');

                static::assertInstanceOf(StatsResult::class, $aggregation);
                static::assertSame($case['min'], $aggregation->getMin(), \sprintf('Case `%s` failed', $message));
                static::assertSame($case['max'], $aggregation->getMax(), \sprintf('Case `%s` failed', $message));
            }
        } catch (\Exception $e) {
            static::tearDown();

            throw $e;
        }
    }

    /**
     * @return iterable<string, array{ids: array<string>, rules: array<string>}>
     */
    public function providerSorting(): iterable
    {
        yield 'Test sorting without rules' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.1', 'v.7.2', 'v.8.1', 'v.8.2', 'v.10.2', 'v.9.1', 'v.10.1', 'v.9.2', 'v.11.1', 'v.11.2', 'v.12.1', 'v.12.2', 'v.14.1', 'v.14.2', 'v.13.1', 'v.13.2'],
            'rules' => [],
        ];

        yield 'Test sorting with rule a' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.2', 'v.10.2', 'v.7.1', 'v.10.1', 'v.8.1', 'v.9.1', 'v.9.2', 'v.8.2', 'v.11.1', 'v.11.2', 'v.14.1', 'v.14.2', 'v.12.2', 'v.12.1', 'v.13.2', 'v.13.1'],
            'rules' => ['rule-a'],
        ];

        yield 'Test sorting with rule b' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.1', 'v.7.2', 'v.8.1', 'v.8.2', 'v.10.2', 'v.9.1', 'v.10.1', 'v.9.2', 'v.12.1', 'v.14.1', 'v.14.2', 'v.11.1', 'v.11.2', 'v.12.2', 'v.13.1', 'v.13.2'],
            'rules' => ['rule-b'],
        ];

        yield 'Test sorting with rule a+b' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.2', 'v.10.2', 'v.7.1', 'v.10.1', 'v.8.1', 'v.9.1', 'v.9.2', 'v.8.2', 'v.11.1', 'v.11.2', 'v.14.1', 'v.14.2', 'v.12.2', 'v.12.1', 'v.13.2', 'v.13.1'],
            'rules' => ['rule-a', 'rule-b'],
        ];

        yield 'Test sorting with rule b+a' => [
            'ids' => ['v.4.1', 'p.1', 'v.4.2', 'v.2.2', 'v.2.1', 'v.3.1', 'v.3.2', 'p.5', 'v.6.1', 'v.6.2', 'v.7.2', 'v.10.2', 'v.7.1', 'v.10.1', 'v.8.1', 'v.9.1', 'v.9.2', 'v.8.2', 'v.14.1', 'v.14.2', 'v.11.1', 'v.11.2', 'v.12.2', 'v.13.2', 'v.12.1', 'v.13.1'],
            'rules' => ['rule-b', 'rule-a'],
        ];
    }

    /**
     * @return iterable<string, array{from: int, rules?: array<string>, to: int, expected: array<string>}>
     */
    public function providerFilterPercentage(): iterable
    {
        yield 'Test 10% filter without rule' => ['from' => 9, 'to' => 10, 'expected' => ['p.1', 'v.4.2']];
        yield 'Test 20% filter with rule-a' => ['rules' => ['rule-a'], 'from' => 19, 'to' => 20, 'expected' => ['p.5', 'v.6.1']];
        yield 'Test 30% filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 29, 'to' => 30, 'expected' => ['v.12.2', 'v.13.2']];
        yield 'Test 30% filter with rule b+a' => ['rules' => ['rule-b'], 'from' => 29, 'to' => 30, 'expected' => ['v.12.2', 'v.13.2']];
        yield 'Test 30% filter with rule a and empty result' => ['rules' => ['rule-a'], 'from' => 29, 'to' => 30, 'expected' => []];
    }

    /**
     * @return iterable<string, array{from: int, to: int, expected: array<string>, rules?: array<string>}>
     */
    public function providerFilterPrice(): iterable
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
        yield 'Test 180€ filter without rule' => ['from' => 180, 'to' => 181, 'expected' => ['v.12.1', 'v.12.2', 'v.14.1', 'v.14.2']];
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
        yield 'Test 180€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 180, 'to' => 181, 'expected' => ['v.14.1', 'v.14.2']];
        yield 'Test 190€ filter with rule b+a' => ['rules' => ['rule-b', 'rule-a'], 'from' => 190, 'to' => 191, 'expected' => ['v.11.1', 'v.11.2', 'v.12.2']];
    }

    /**
     * @return iterable<string, array{rules: array<string>, currencyId: string, assertions: array<string, array{cheapest: float, price: float, prices: array<float>}>}>
     */
    private function calculationProvider(IdsCollection $ids): iterable
    {
        yield 'test without rule' => [
            'rules' => [],
            'currencyId' => Defaults::CURRENCY,
            'assertions' => [
                'p.1' => ['cheapest' => 70.0,  'price' => 70.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 79.0,  'price' => 80.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 79.0,  'price' => 79.0, 'prices' => []],
                'p.3' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 90.0, 'price' => 100.0, 'prices' => []],
                'p.4' => ['cheapest' => 60.0, 'price' => 100.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 60.0,  'price' => 60.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 60.0,  'price' => 70.0, 'prices' => []],
                'p.5' => ['cheapest' => 110.0, 'price' => 110.0, 'prices' => []],
                'p.6' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'v.6.1' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'v.6.2' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'p.7' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'v.7.1' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'v.7.2' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'p.8' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'v.8.1' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'v.8.2' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'p.9' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'v.9.2' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'p.10' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.2' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'p.11' => ['cheapest' => 170.0, 'price' => 170.0, 'prices' => []],
                'v.11.1' => ['cheapest' => 170.0, 'price' => 170.0, 'prices' => []],
                'v.11.2' => ['cheapest' => 170.0, 'price' => 170.0, 'prices' => []],
                'p.12' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.12.2' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'p.13' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'v.13.1' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'v.13.2' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'p.14' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 180.0, 'price' => 181.0, 'prices' => []],
            ],
        ];
        yield 'test with rule a' => [
            'rules' => [$ids->get('rule-a')],
            'currencyId' => Defaults::CURRENCY,
            'assertions' => [
                'p.1' => ['cheapest' => 70.0,  'price' => 70.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 79.0,  'price' => 80.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 79.0,  'price' => 79.0, 'prices' => []],
                'p.3' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 90.0, 'price' => 100.0, 'prices' => []],
                'p.4' => ['cheapest' => 60.0, 'price' => 100.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 60.0,  'price' => 60.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 60.0,  'price' => 70.0, 'prices' => []],
                'p.5' => ['cheapest' => 120.0, 'price' => 110.0, 'prices' => [130.0, 120.0]],
                'p.6' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [150.0, 140.0]],
                'v.6.1' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [140.0, 130.0]],
                'v.6.2' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [150.0, 140.0]],
                'p.7' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [150.0, 140.0]],
                'v.7.1' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [160.0, 150.0]],
                'v.7.2' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [150.0, 140.0]],
                'p.8' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [160.0, 150.0]],
                'v.8.1' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [170.0, 160.0]],
                'v.8.2' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [180.0, 170.0]],
                'p.9' => ['cheapest' => 160.0, 'price' => 150.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 160.0, 'price' => 150.0, 'prices' => [170.0, 160.0]],
                'v.9.2' => ['cheapest' => 160.0, 'price' => 160.0, 'prices' => []],
                'p.10' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => [170.0, 160.0]],
                'v.10.2' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'p.11' => ['cheapest' => 180.0, 'price' => 170.0, 'prices' => [190.0, 180.0]],
                'v.11.1' => ['cheapest' => 180.0, 'price' => 170.0, 'prices' => [190.0, 180.0]],
                'v.11.2' => ['cheapest' => 180.0, 'price' => 170.0, 'prices' => [190.0, 180.0]],
                'p.12' => ['cheapest' => 200.0, 'price' => 180.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 200.0, 'price' => 180.0, 'prices' => [220.0, 210.0]],
                'v.12.2' => ['cheapest' => 200.0, 'price' => 180.0, 'prices' => [210.0, 200.0]],
                'p.13' => ['cheapest' => 210.0, 'price' => 190.0, 'prices' => [230.0, 220.0]],
                'v.13.1' => ['cheapest' => 210.0, 'price' => 190.0, 'prices' => [230.0, 220.0]],
                'v.13.2' => ['cheapest' => 210.0, 'price' => 190.0, 'prices' => [220.0, 210.0]],
                'p.14' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 180.0, 'price' => 181.0, 'prices' => []],
            ],
        ];
        yield 'test with rule b' => [
            'rules' => [$ids->get('rule-b')],
            'currencyId' => Defaults::CURRENCY,
            'assertions' => [
                'p.1' => ['cheapest' => 70.0,  'price' => 70.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 79.0,  'price' => 80.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 79.0,  'price' => 79.0, 'prices' => []],
                'p.3' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 90.0, 'price' => 100.0, 'prices' => []],
                'p.4' => ['cheapest' => 60.0, 'price' => 100.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 60.0,  'price' => 60.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 60.0,  'price' => 70.0, 'prices' => []],
                'p.5' => ['cheapest' => 110.0, 'price' => 110.0, 'prices' => []],
                'p.6' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'v.6.1' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'v.6.2' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'p.7' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'v.7.1' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'v.7.2' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'p.8' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'v.8.1' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'v.8.2' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'p.9' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'v.9.2' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'p.10' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.2' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'p.11' => ['cheapest' => 190.0, 'price' => 170.0, 'prices' => [200.0, 190.0]],
                'v.11.1' => ['cheapest' => 190.0, 'price' => 170.0, 'prices' => [200.0, 190.0]],
                'v.11.2' => ['cheapest' => 190.0, 'price' => 170.0, 'prices' => [200.0, 190.0]],
                'p.12' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.12.2' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => [200.0, 190.0]],
                'p.13' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'v.13.1' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'v.13.2' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => [210.0, 200.0]],
                'p.14' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 180.0, 'price' => 181.0, 'prices' => []],
            ],
        ];
        yield 'test with rule a+b' => [
            'rules' => [$ids->get('rule-a'), $ids->get('rule-b')],
            'currencyId' => Defaults::CURRENCY,
            'assertions' => [
                'p.1' => ['cheapest' => 70.0,  'price' => 70.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 79.0,  'price' => 80.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 79.0,  'price' => 79.0, 'prices' => []],
                'p.3' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 90.0, 'price' => 100.0, 'prices' => []],
                'p.4' => ['cheapest' => 60.0, 'price' => 100.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 60.0,  'price' => 60.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 60.0,  'price' => 70.0, 'prices' => []],
                'p.5' => ['cheapest' => 120.0, 'price' => 110.0, 'prices' => [130.0, 120.0]],
                'p.6' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [150.0, 140.0]],
                'v.6.1' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [140.0, 130.0]],
                'v.6.2' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [150.0, 140.0]],
                'p.7' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [150.0, 140.0]],
                'v.7.1' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [160.0, 150.0]],
                'v.7.2' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [150.0, 140.0]],
                'p.8' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [160.0, 150.0]],
                'v.8.1' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [170.0, 160.0]],
                'v.8.2' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [180.0, 170.0]],
                'p.9' => ['cheapest' => 160.0, 'price' => 150.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 160.0, 'price' => 150.0, 'prices' => [170.0, 160.0]],
                'v.9.2' => ['cheapest' => 160.0, 'price' => 160.0, 'prices' => []],
                'p.10' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => [170.0, 160.0]],
                'v.10.2' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'p.11' => ['cheapest' => 180.0, 'price' => 170.0, 'prices' => [190.0, 180.0]],
                'v.11.1' => ['cheapest' => 180.0, 'price' => 170.0, 'prices' => [190.0, 180.0]],
                'v.11.2' => ['cheapest' => 180.0, 'price' => 170.0, 'prices' => [190.0, 180.0]],
                'p.12' => ['cheapest' => 200.0, 'price' => 180.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 200.0, 'price' => 180.0, 'prices' => [220.0, 210.0]],
                'v.12.2' => ['cheapest' => 200.0, 'price' => 180.0, 'prices' => [210.0, 200.0]],
                'p.13' => ['cheapest' => 210.0, 'price' => 190.0, 'prices' => [230.0, 220.0]],
                'v.13.1' => ['cheapest' => 210.0, 'price' => 190.0, 'prices' => [230.0, 220.0]],
                'v.13.2' => ['cheapest' => 210.0, 'price' => 190.0, 'prices' => [220.0, 210.0]],
                'p.14' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 180.0, 'price' => 181.0, 'prices' => []],
            ],
        ];
        yield 'test with rule b+a' => [
            'rules' => [$ids->get('rule-b'), $ids->get('rule-a')],
            'currencyId' => Defaults::CURRENCY,
            'assertions' => [
                'p.1' => ['cheapest' => 70.0,  'price' => 70.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 79.0,  'price' => 80.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 79.0,  'price' => 79.0, 'prices' => []],
                'p.3' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 90.0, 'price' => 100.0, 'prices' => []],
                'p.4' => ['cheapest' => 60.0,  'price' => 100.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 60.0,  'price' => 60.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 60.0,  'price' => 70.0, 'prices' => []],
                'p.5' => ['cheapest' => 120.0, 'price' => 110.0, 'prices' => [130.0, 120.0]],
                'p.6' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [150.0, 140.0]],
                'v.6.1' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [140.0, 130.0]],
                'v.6.2' => ['cheapest' => 130.0, 'price' => 120.0, 'prices' => [150.0, 140.0]],
                'p.7' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [150.0, 140.0]],
                'v.7.1' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [160.0, 150.0]],
                'v.7.2' => ['cheapest' => 140.0, 'price' => 130.0, 'prices' => [150.0, 140.0]],
                'p.8' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [160.0, 150.0]],
                'v.8.1' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [170.0, 160.0]],
                'v.8.2' => ['cheapest' => 160.0, 'price' => 140.0, 'prices' => [180.0, 170.0]],
                'p.9' => ['cheapest' => 160.0, 'price' => 150.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 160.0, 'price' => 150.0, 'prices' => [170.0, 160.0]],
                'v.9.2' => ['cheapest' => 160.0, 'price' => 160.0, 'prices' => []],
                'p.10' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => [170.0, 160.0]],
                'v.10.2' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'p.11' => ['cheapest' => 190.0, 'price' => 170.0, 'prices' => [200.0, 190.0]],
                'v.11.1' => ['cheapest' => 190.0, 'price' => 170.0, 'prices' => [200.0, 190.0]],
                'v.11.2' => ['cheapest' => 190.0, 'price' => 170.0, 'prices' => [200.0, 190.0]],
                'p.12' => ['cheapest' => 190.0, 'price' => 180.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 190.0, 'price' => 180.0, 'prices' => [220.0, 210.0]],
                'v.12.2' => ['cheapest' => 190.0, 'price' => 180.0, 'prices' => [200.0, 190.0]],
                'p.13' => ['cheapest' => 200.0, 'price' => 190.0, 'prices' => [230.0, 220.0]],
                'v.13.1' => ['cheapest' => 200.0, 'price' => 190.0, 'prices' => [230.0, 220.0]],
                'v.13.2' => ['cheapest' => 200.0, 'price' => 190.0, 'prices' => [210.0, 200.0]],
                'p.14' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 180.0, 'price' => 181.0, 'prices' => []],
            ],
        ];
        yield 'test with rule c' => [
            'rules' => [$ids->get('rule-c')],
            'currencyId' => Defaults::CURRENCY,
            'assertions' => [
                'p.1' => ['cheapest' => 70.0,  'price' => 70.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 79.0,  'price' => 80.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 79.0,  'price' => 79.0, 'prices' => []],
                'p.3' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 90.0,  'price' => 90.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 90.0, 'price' => 100.0, 'prices' => []],
                'p.4' => ['cheapest' => 60.0,  'price' => 100.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 60.0,  'price' => 60.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 60.0,  'price' => 70.0, 'prices' => []],
                'p.5' => ['cheapest' => 110.0, 'price' => 110.0, 'prices' => []],
                'p.6' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'v.6.1' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'v.6.2' => ['cheapest' => 120.0, 'price' => 120.0, 'prices' => []],
                'p.7' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'v.7.1' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'v.7.2' => ['cheapest' => 130.0, 'price' => 130.0, 'prices' => []],
                'p.8' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'v.8.1' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'v.8.2' => ['cheapest' => 140.0, 'price' => 140.0, 'prices' => []],
                'p.9' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'v.9.2' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'p.10' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 150.0, 'price' => 160.0, 'prices' => []],
                'v.10.2' => ['cheapest' => 150.0, 'price' => 150.0, 'prices' => []],
                'p.11' => ['cheapest' => 170.0, 'price' => 170.0, 'prices' => []],
                'v.11.1' => ['cheapest' => 170.0, 'price' => 170.0, 'prices' => []],
                'v.11.2' => ['cheapest' => 170.0, 'price' => 170.0, 'prices' => []],
                'p.12' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.12.2' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'p.13' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'v.13.1' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'v.13.2' => ['cheapest' => 190.0, 'price' => 190.0, 'prices' => []],
                'p.14' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 180.0, 'price' => 181.0, 'prices' => []],
            ],
        ];

        // # testing of same cases but with other currency then default ##
        yield 'test without rule and other currency' => [
            'rules' => [],
            'currencyId' => $ids->get('currency'),
            'assertions' => [
                'p.1' => ['cheapest' => 99.0, 'price' => 99.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 88.0, 'price' => 160.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 88.0, 'price' => 88.0, 'prices' => []],
                'p.3' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 180.0, 'price' => 200.0, 'prices' => []],
                'p.4' => ['cheapest' => 101.0, 'price' => 200.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 101.0, 'price' => 120.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 101.0, 'price' => 101.0, 'prices' => []],
                'p.5' => ['cheapest' => 220.0, 'price' => 220.0, 'prices' => []],
                'p.6' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'v.6.1' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'v.6.2' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'p.7' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'v.7.1' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'v.7.2' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'p.8' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'v.8.1' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'v.8.2' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'p.9' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'v.9.2' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'p.10' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.2' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'p.11' => ['cheapest' => 340.0, 'price' => 340.0, 'prices' => []],
                'v.11.1' => ['cheapest' => 340.0, 'price' => 340.0, 'prices' => []],
                'v.11.2' => ['cheapest' => 340.0, 'price' => 340.0, 'prices' => []],
                'p.12' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.12.2' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'p.13' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'v.13.1' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'v.13.2' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'p.14' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 360.0, 'price' => 362.0, 'prices' => []],
            ],
        ];
        yield 'test with rule a and other currency' => [
            'rules' => [$ids->get('rule-a')],
            'currencyId' => $ids->get('currency'),
            'assertions' => [
                'p.1' => ['cheapest' => 99.0, 'price' => 99.0,   'prices' => []],
                'v.2.1' => ['cheapest' => 88.0, 'price' => 160.0,  'prices' => []],
                'v.2.2' => ['cheapest' => 88.0, 'price' => 88.0,   'prices' => []],
                'p.3' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 180.0, 'price' => 200.0, 'prices' => []],
                'p.4' => ['cheapest' => 101.0, 'price' => 200.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 101.0, 'price' => 120.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 101.0, 'price' => 101.0, 'prices' => []],
                'p.5' => ['cheapest' => 240.0, 'price' => 220.0, 'prices' => [260.0, 240.0]],
                'p.6' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [199.0, 188.0]],
                'v.6.1' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [188.0, 177.0]],
                'v.6.2' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [199.0, 188.0]],
                'p.7' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [300.0, 280.0]],
                'v.7.1' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [320.0, 300.0]],
                'v.7.2' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [300.0, 280.0]],
                'p.8' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [320.0, 300.0]],
                'v.8.1' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [340.0, 320.0]],
                'v.8.2' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [360.0, 340.0]],
                'p.9' => ['cheapest' => 320.0, 'price' => 300.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 320.0, 'price' => 300.0, 'prices' => [340.0, 320.0]],
                'v.9.2' => ['cheapest' => 320.0, 'price' => 320.0, 'prices' => []],
                'p.10' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => [340.0, 320.0]],
                'v.10.2' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'p.11' => ['cheapest' => 360.0, 'price' => 340.0, 'prices' => [380.0, 360.0]],
                'v.11.1' => ['cheapest' => 360.0, 'price' => 340.0, 'prices' => [380.0, 360.0]],
                'v.11.2' => ['cheapest' => 360.0, 'price' => 340.0, 'prices' => [380.0, 360.0]],
                'p.12' => ['cheapest' => 400.0, 'price' => 360.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 400.0, 'price' => 360.0, 'prices' => [440.0, 420.0]],
                'v.12.2' => ['cheapest' => 400.0, 'price' => 360.0, 'prices' => [420.0, 400.0]],
                'p.13' => ['cheapest' => 420.0, 'price' => 380.0, 'prices' => [460.0, 440.0]],
                'v.13.1' => ['cheapest' => 420.0, 'price' => 380.0, 'prices' => [460.0, 440.0]],
                'v.13.2' => ['cheapest' => 420.0, 'price' => 380.0, 'prices' => [440.0, 420.0]],
                'p.14' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 360.0, 'price' => 362.0, 'prices' => []],
            ],
        ];
        yield 'test with rule b and other currency' => [
            'rules' => [$ids->get('rule-b')],
            'currencyId' => $ids->get('currency'),
            'assertions' => [
                'p.1' => ['cheapest' => 99.0, 'price' => 99.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 88.0, 'price' => 160.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 88.0, 'price' => 88.0, 'prices' => []],
                'p.3' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 180.0, 'price' => 200.0, 'prices' => []],
                'p.4' => ['cheapest' => 101.0, 'price' => 200.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 101.0, 'price' => 120.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 101.0, 'price' => 101.0, 'prices' => []],
                'p.5' => ['cheapest' => 220.0, 'price' => 220.0, 'prices' => []],
                'p.6' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'v.6.1' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'v.6.2' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'p.7' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'v.7.1' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'v.7.2' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'p.8' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'v.8.1' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'v.8.2' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'p.9' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'v.9.2' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'p.10' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.2' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'p.11' => ['cheapest' => 380.0, 'price' => 340.0, 'prices' => [400.0, 380.0]],
                'v.11.1' => ['cheapest' => 380.0, 'price' => 340.0, 'prices' => [400.0, 380.0]],
                'v.11.2' => ['cheapest' => 380.0, 'price' => 340.0, 'prices' => [400.0, 380.0]],
                'p.12' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.12.2' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => [400.0, 380.0]],
                'p.13' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'v.13.1' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'v.13.2' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => [420.0, 400.0]],
                'p.14' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 360.0, 'price' => 362.0, 'prices' => []],
            ],
        ];

        yield 'test with rule a+b and other currency' => [
            'rules' => [$ids->get('rule-a'), $ids->get('rule-b')],
            'currencyId' => $ids->get('currency'),
            'assertions' => [
                'p.1' => ['cheapest' => 99.0, 'price' => 99.0,   'prices' => []],
                'v.2.1' => ['cheapest' => 88.0, 'price' => 160.0,  'prices' => []],
                'v.2.2' => ['cheapest' => 88.0, 'price' => 88.0,   'prices' => []],
                'p.3' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 180.0, 'price' => 200.0, 'prices' => []],
                'p.4' => ['cheapest' => 101.0, 'price' => 200.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 101.0, 'price' => 120.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 101.0, 'price' => 101.0, 'prices' => []],
                'p.5' => ['cheapest' => 240.0, 'price' => 220.0, 'prices' => [260.0, 240.0]],
                'p.6' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [199.0, 188.0]],
                'v.6.1' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [188.0, 177.0]],
                'v.6.2' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [199.0, 188.0]],
                'p.7' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [300.0, 280.0]],
                'v.7.1' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [320.0, 300.0]],
                'v.7.2' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [300.0, 280.0]],
                'p.8' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [320.0, 300.0]],
                'v.8.1' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [340.0, 320.0]],
                'v.8.2' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [360.0, 340.0]],
                'p.9' => ['cheapest' => 320.0, 'price' => 300.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 320.0, 'price' => 300.0, 'prices' => [340.0, 320.0]],
                'v.9.2' => ['cheapest' => 320.0, 'price' => 320.0, 'prices' => []],
                'p.10' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => [340.0, 320.0]],
                'v.10.2' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'p.11' => ['cheapest' => 360.0, 'price' => 340.0, 'prices' => [380.0, 360.0]],
                'v.11.1' => ['cheapest' => 360.0, 'price' => 340.0, 'prices' => [380.0, 360.0]],
                'v.11.2' => ['cheapest' => 360.0, 'price' => 340.0, 'prices' => [380.0, 360.0]],
                'p.12' => ['cheapest' => 400.0, 'price' => 360.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 400.0, 'price' => 360.0, 'prices' => [440.0, 420.0]],
                'v.12.2' => ['cheapest' => 400.0, 'price' => 360.0, 'prices' => [420.0, 400.0]],
                'p.13' => ['cheapest' => 420.0, 'price' => 380.0, 'prices' => [460.0, 440.0]],
                'v.13.1' => ['cheapest' => 420.0, 'price' => 380.0, 'prices' => [460.0, 440.0]],
                'v.13.2' => ['cheapest' => 420.0, 'price' => 380.0, 'prices' => [440.0, 420.0]],
                'p.14' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 360.0, 'price' => 362.0, 'prices' => []],
            ],
        ];

        yield 'test with rule b+a and other currency' => [
            'rules' => [$ids->get('rule-b'), $ids->get('rule-a')],
            'currencyId' => $ids->get('currency'),
            'assertions' => [
                'p.1' => ['cheapest' => 99.0, 'price' => 99.0,   'prices' => []],
                'v.2.1' => ['cheapest' => 88.0, 'price' => 160.0,  'prices' => []],
                'v.2.2' => ['cheapest' => 88.0, 'price' => 88.0,   'prices' => []],
                'p.3' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 180.0, 'price' => 200.0, 'prices' => []],
                'p.4' => ['cheapest' => 101.0, 'price' => 200.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 101.0, 'price' => 120.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 101.0, 'price' => 101.0, 'prices' => []],
                'p.5' => ['cheapest' => 240.0, 'price' => 220.0, 'prices' => [260.0, 240.0]],
                'p.6' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [199.0, 188.0]],
                'v.6.1' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [188.0, 177.0]],
                'v.6.2' => ['cheapest' => 177.0, 'price' => 240.0, 'prices' => [199.0, 188.0]],
                'p.7' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [300.0, 280.0]],
                'v.7.1' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [320.0, 300.0]],
                'v.7.2' => ['cheapest' => 280.0, 'price' => 260.0, 'prices' => [300.0, 280.0]],
                'p.8' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [320.0, 300.0]],
                'v.8.1' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [340.0, 320.0]],
                'v.8.2' => ['cheapest' => 320.0, 'price' => 280.0, 'prices' => [360.0, 340.0]],
                'p.9' => ['cheapest' => 320.0, 'price' => 300.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 320.0, 'price' => 300.0, 'prices' => [340.0, 320.0]],
                'v.9.2' => ['cheapest' => 320.0, 'price' => 320.0, 'prices' => []],
                'p.10' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => [340.0, 320.0]],
                'v.10.2' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'p.11' => ['cheapest' => 380.0, 'price' => 340.0, 'prices' => [400.0, 380.0]],
                'v.11.1' => ['cheapest' => 380.0, 'price' => 340.0, 'prices' => [400.0, 380.0]],
                'v.11.2' => ['cheapest' => 380.0, 'price' => 340.0, 'prices' => [400.0, 380.0]],
                'p.12' => ['cheapest' => 380.0, 'price' => 360.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 380.0, 'price' => 360.0, 'prices' => [440.0, 420.0]],
                'v.12.2' => ['cheapest' => 380.0, 'price' => 360.0, 'prices' => [400.0, 380.0]],
                'p.13' => ['cheapest' => 400.0, 'price' => 380.0, 'prices' => [460.0, 440.0]],
                'v.13.1' => ['cheapest' => 400.0, 'price' => 380.0, 'prices' => [460.0, 440.0]],
                'v.13.2' => ['cheapest' => 400.0, 'price' => 380.0, 'prices' => [420.0, 400.0]],
                'p.14' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 360.0, 'price' => 362.0, 'prices' => []],
            ],
        ];

        yield 'test with rule c and other currency' => [
            'rules' => [$ids->get('rule-c')],
            'currencyId' => $ids->get('currency'),
            'assertions' => [
                'p.1' => ['cheapest' => 99.0, 'price' => 99.0, 'prices' => []],
                'v.2.1' => ['cheapest' => 88.0, 'price' => 160.0, 'prices' => []],
                'v.2.2' => ['cheapest' => 88.0, 'price' => 88.0, 'prices' => []],
                'p.3' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.1' => ['cheapest' => 180.0, 'price' => 180.0, 'prices' => []],
                'v.3.2' => ['cheapest' => 180.0, 'price' => 200.0, 'prices' => []],
                'p.4' => ['cheapest' => 101.0, 'price' => 200.0, 'prices' => []],
                'v.4.1' => ['cheapest' => 101.0, 'price' => 120.0, 'prices' => []],
                'v.4.2' => ['cheapest' => 101.0, 'price' => 101.0, 'prices' => []],
                'p.5' => ['cheapest' => 220.0, 'price' => 220.0, 'prices' => []],
                'p.6' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'v.6.1' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'v.6.2' => ['cheapest' => 240.0, 'price' => 240.0, 'prices' => []],
                'p.7' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'v.7.1' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'v.7.2' => ['cheapest' => 260.0, 'price' => 260.0, 'prices' => []],
                'p.8' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'v.8.1' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'v.8.2' => ['cheapest' => 280.0, 'price' => 280.0, 'prices' => []],
                'p.9' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'v.9.1' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'v.9.2' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'p.10' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.1' => ['cheapest' => 300.0, 'price' => 320.0, 'prices' => []],
                'v.10.2' => ['cheapest' => 300.0, 'price' => 300.0, 'prices' => []],
                'p.11' => ['cheapest' => 340.0, 'price' => 340.0, 'prices' => []],
                'v.11.1' => ['cheapest' => 340.0, 'price' => 340.0, 'prices' => []],
                'v.11.2' => ['cheapest' => 340.0, 'price' => 340.0, 'prices' => []],
                'p.12' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.12.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.12.2' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'p.13' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'v.13.1' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'v.13.2' => ['cheapest' => 380.0, 'price' => 380.0, 'prices' => []],
                'p.14' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.1' => ['cheapest' => 360.0, 'price' => 360.0, 'prices' => []],
                'v.14.2' => ['cheapest' => 360.0, 'price' => 362.0, 'prices' => []],
            ],
        ];
    }

    /**
     * @param array{ids: array<string>, rules: array<string>} $case
     */
    private function assertSorting(string $message, IdsCollection $ids, SalesChannelContext $context, array $case, string $direction): void
    {
        $criteria = new Criteria(array_values($ids->all()));

        $criteria->addSorting(new FieldSorting('product.cheapestPrice', $direction));
        $criteria->addSorting(new FieldSorting('product.productNumber', $direction));

        $criteria->addFilter(new OrFilter([
            new NandFilter([new EqualsFilter('product.parentId', null)]),
            new EqualsFilter('product.childCount', 0),
        ]));

        $result = static::getContainer()->get('sales_channel.product.repository')
            ->searchIds($criteria, $context);

        $expected = $case['ids'];
        if ($direction === FieldSorting::DESCENDING) {
            $expected = array_reverse($expected);
        }

        /** @var string[] $actual */
        $actual = array_values($result->getIds());

        $actualArray = [];
        foreach ($actual as $id) {
            $actualArray[] = $ids->getKey($id);
        }

        static::assertSame($expected, $actualArray, $message);
    }

    /**
     * @return iterable<string, array{min: string, max: string, rules: array<string>}>
     */
    private function providerAggregation(): iterable
    {
        yield 'With no rules' => ['min' => '60.0000', 'max' => '190.0000', 'rules' => []];
        yield 'With rule a' => ['min' => '60.0000', 'max' => '220.0000', 'rules' => ['rule-a']];
        yield 'With rule b' => ['min' => '60.0000', 'max' => '200.0000', 'rules' => ['rule-b']];
        yield 'With rule a+b' => ['min' => '60.0000', 'max' => '220.0000', 'rules' => ['rule-a', 'rule-b']];
        yield 'With rule b+a' => ['min' => '60.0000', 'max' => '220.0000', 'rules' => ['rule-b', 'rule-a']];
    }
}
