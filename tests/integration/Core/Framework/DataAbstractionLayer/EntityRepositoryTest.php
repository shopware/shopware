<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(EntityRepository::class)]
class EntityRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function testReverseVersionJoin(): void
    {
        $repository = $this->getContainer()->get('product_visibility.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.orderLineItems.order.id', Uuid::randomHex()));
        $criteria->addFilter(new EqualsFilter('product.orderLineItems.type', LineItem::PRODUCT_LINE_ITEM_TYPE));

        $result = $repository->search($criteria, Context::createDefaultContext());

        static::assertEquals(0, $result->count());
    }

    /**
     * @param array<string, mixed> $products
     * @param array<string> $expected
     */
    #[DataProvider('productPropertiesQueryProvider')]
    public function testProductPropertiesQueries(array $products, Criteria $criteria, array $expected): void
    {
        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $found = $this->getContainer()
            ->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(\count($expected), $found->getIds());

        foreach ($expected as $id) {
            static::assertContains($id, $found->getIds());
        }
    }

    public static function productPropertiesQueryProvider(): \Generator
    {
        $ids = new IdsCollection();
        yield 'Test matching single property' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('properties.id', $ids->get('red'))),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test matching multiple properties of same group' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'green' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('properties.id', $ids->get('red')))
                ->addFilter(new EqualsFilter('properties.id', $ids->get('green'))),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test matching multiple properties of same group, not fit' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('properties.id', $ids->get('red')))
                ->addFilter(new EqualsFilter('properties.id', $ids->get('green'))),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.groupId', $ids->get('color')),
                ])),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id, not fit' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.groupId', $ids->get('size')),
                ])),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id using association, matches' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.group.id', $ids->get('color')),
                ])),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id using association, not matches' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'size', 'red' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.group.id', $ids->get('size')),
                ])),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test for jonas' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new AndFilter([
                        new EqualsFilter('properties.id', $ids->get('red')),
                        new EqualsFilter('properties.group.id', $ids->get('size')),
                    ]),
                ),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Works with no nested association' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('properties.id', $ids->get('red')),
                            new EqualsFilter('properties.groupId', $ids->get('size')),
                        ]),
                        new EqualsFilter('properties.id', $ids->get('blue')),
                    ])
                ),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Works with nested association and or filter' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('properties.id', $ids->get('red')),
                            new EqualsFilter('properties.group.id', $ids->get('size')),
                        ]),
                        new EqualsFilter('active', true),
                    ])
                ),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test not product fits the multi or filter and nested and filter' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('properties.id', $ids->get('red')),
                            new EqualsFilter('properties.group.id', $ids->get('size')),
                        ]),
                        new EqualsFilter('properties.id', $ids->get('blue')),
                    ])
                ),
            [],
        ];
    }

    /**
     * @param array<array{payment: string, state:string}> $transactions
     */
    #[DataProvider('orderTransactionsProvider')]
    public function testOrderTransactionsQueries(array $transactions, Criteria $criteria, bool $match): void
    {
        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=0;');

        $ids = new IdsCollection();

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $queue->addInsert('order', self::order($ids->get('order-id')));

        foreach ($transactions as $transaction) {
            $queue->addInsert(
                'order_transaction',
                $this->transaction($ids->get('order-id'), $transaction['payment'], $transaction['state'])
            );
        }

        $queue->execute();

        $found = $this->getContainer()
            ->get('order.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        $found = !empty($found->getIds());

        static::assertEquals($match, $found);
    }

    public static function orderTransactionsProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Matching by just check for payment method' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal'))),
            true,
        ];

        yield 'Multi filter on transactions works, matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.amount.unitPrice', 100),
                ])),
            true,
        ];

        yield 'Multi filter on transactions works, not matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.amount.unitPrice', 110),
                ])),
            false,
        ];

        yield 'Match exact state of payment method' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'open'),
                ])),
            true,
        ];

        yield 'Payment method exists but state is not matching' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                ])),
            false,
        ];

        yield 'Has open paypal or another paid transaction, matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'cancelled'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                            new EqualsFilter('transactions.stateMachineState.technicalName', 'open'),
                        ]),
                        new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                    ]),
                ),
            true,
        ];

        yield 'Has open paypal or another paid transaction, does not match' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'cancelled'],
                ['payment' => $ids->get('invoice'), 'state' => 'open'],
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                            new EqualsFilter('transactions.stateMachineState.technicalName', 'open'),
                        ]),
                        new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                    ]),
                ),
            false,
        ];
    }

    /**
     * @param array<string, string> $properties
     *
     * @return array<string, mixed>
     */
    private static function product(IdsCollection $ids, string $key, array $properties): array
    {
        $builder = new ProductBuilder($ids, $key);
        $builder->price(100);
        foreach ($properties as $value => $group) {
            $builder->property($value, $group);
        }
        $builder->active(false);

        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    private static function order(string $id): array
    {
        return [
            'id' => Uuid::fromHexToBytes($id),
            'currency_factor' => 1.0,
            'order_date_time' => '2020-01-01 00:00:00.000000',
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'price' => json_encode([
                'netPrice' => 100,
                'taxStatus' => 'gross',
                'totalPrice' => 100,
                'positionPrice' => 1,
            ]),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'state_id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billing_address_id' => Uuid::randomBytes(),
            'billing_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'shipping_costs' => '{}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(string $orderId, string $payment, string $state): array
    {
        $machineId = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT id FROM state_machine WHERE technical_name = :state', ['state' => 'order_transaction.state']);

        $stateId = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :state AND state_machine_id = :machineId', ['state' => $state, 'machineId' => $machineId]);

        return [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'payment_method_id' => Uuid::fromHexToBytes($payment),
            'state_id' => $stateId,
            'amount' => json_encode(['unitPrice' => 100]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }
}
