<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Dashboard;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Dashboard\OrderAmountService;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class OrderAmountServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * @param array<array<string, mixed>> $orders
     * @param array<array<string, mixed>> $expected
     */
    #[DataProvider('loadProvider')]
    public function testLoad(array $orders, array $expected, string $since, bool $paid): void
    {
        $states = $this->getContainer()->get(Connection::class)->fetchAllKeyValue(
            'SELECT technical_name, LOWER(HEX(id)) FROM state_machine_state WHERE technical_name IN (:states)',
            ['states' => [OrderTransactionStates::STATE_PAID, OrderStates::STATE_OPEN]],
            ['states' => ArrayParameterType::STRING]
        );

        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=0;');

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        foreach ($orders as $order) {
            $order['state_id'] = Uuid::fromHexToBytes($states[$order['state_id']]);

            $queue->addInsert('order', $order);

            $queue->addInsert('order_transaction', [
                'id' => Uuid::randomBytes(),
                'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'order_id' => $order['id'],
                'payment_method_id' => Uuid::randomBytes(),
                'state_id' => $order['state_id'],
                'amount' => 19.99,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        $queue->execute();

        $service = new OrderAmountService(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(CashRounding::class),
            false
        );

        $buckets = $service->load($since, $paid);

        static::assertEquals($expected, $buckets);
    }

    public static function loadProvider(): \Generator
    {
        yield 'Single order, transaction open, not filter paid, no currency factor, within since range' => [
            'orders' => [
                self::order(1, '2021-01-01', 19.99, OrderStates::STATE_OPEN),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 19.99, 'count' => 1],
            ],
            'since' => '2021-01-01',
            'paid' => false,
        ];

        yield 'Single order, transaction open, not filter paid, no currency factor, outside since range' => [
            'orders' => [
                self::order(1, '2020-01-01', 19.99, OrderStates::STATE_OPEN),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => false,
        ];

        yield 'Single order, transaction open, filter paid, no currency factor, within since range' => [
            'orders' => [
                self::order(1, '2021-01-01', 19.99, OrderStates::STATE_OPEN),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Single order, transaction open, filter paid, no currency factor, outside since range' => [
            'orders' => [
                self::order(1, '2020-01-01', 19.99, OrderStates::STATE_OPEN),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Single order, transaction paid, filter paid, no currency factor, within since range' => [
            'orders' => [
                self::order(1, '2021-01-01', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 19.99, 'count' => 1],
            ],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Single order, transaction paid, filter paid, no currency factor, outside since range' => [
            'orders' => [
                self::order(1, '2020-01-01', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Single order, transaction paid, filter paid, currency factor, within since range' => [
            'orders' => [
                self::order(2, '2021-01-01', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 10.00, 'count' => 1],
            ],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Single order, transaction paid, filter paid, currency factor, outside since range' => [
            'orders' => [
                self::order(2, '2020-01-01', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, within since range' => [
            'orders' => [
                self::order(2, '2021-01-01', 20.00, OrderTransactionStates::STATE_PAID),
                self::order(2, '2021-01-01', 100.00, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 60.00, 'count' => 2],
            ],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, outside since range' => [
            'orders' => [
                self::order(2, '2020-01-01', 19.99, OrderTransactionStates::STATE_PAID),
                self::order(2, '2020-01-01', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, within since range, different dates' => [
            'orders' => [
                self::order(2, '2021-01-01', 19.99, OrderTransactionStates::STATE_PAID),
                self::order(2, '2021-01-02', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 10.00, 'count' => 1],
                ['date' => '2021-01-02', 'amount' => 10.00, 'count' => 1],
            ],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, outside since range, different dates' => [
            'orders' => [
                self::order(2, '2020-01-01', 19.99, OrderTransactionStates::STATE_PAID),
                self::order(2, '2020-01-02', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, within since range, different dates, different states' => [
            'orders' => [
                self::order(2, '2021-01-01', 19.99, OrderTransactionStates::STATE_PAID),
                self::order(2, '2021-01-02', 19.99, OrderTransactionStates::STATE_OPEN),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 10.00, 'count' => 1],
            ],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, outside since range, different dates, different states' => [
            'orders' => [
                self::order(2, '2020-01-01', 19.99, OrderTransactionStates::STATE_PAID),
                self::order(2, '2020-01-02', 19.99, OrderTransactionStates::STATE_OPEN),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, within since range, different dates, different states, different currency factors' => [
            'orders' => [
                self::order(2, '2021-01-01', 19.99, OrderTransactionStates::STATE_PAID),
                self::order(3, '2021-01-02', 30.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 10.00, 'count' => 1],
                ['date' => '2021-01-02', 'amount' => 10.33, 'count' => 1],
            ],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders, transaction paid, filter paid, currency factor, outside since range, different dates, different states, different currency factors' => [
            'orders' => [
                self::order(2, '2020-01-01', 19.99, OrderTransactionStates::STATE_PAID),
                self::order(3, '2020-01-02', 19.99, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [],
            'since' => '2021-01-01',
            'paid' => true,
        ];

        yield 'Multiple orders at same date, different currencies, different states' => [
            'orders' => [
                self::order(1, '2021-01-01', 20.00, OrderTransactionStates::STATE_PAID),
                self::order(2, '2021-01-01', 20.00, OrderStates::STATE_OPEN),
                self::order(3, '2021-01-01', 30.00, OrderTransactionStates::STATE_PAID),

                self::order(1, '2021-01-02', 20.00, OrderTransactionStates::STATE_PAID),
                self::order(2, '2021-01-02', 20.00, OrderTransactionStates::STATE_PAID),
                self::order(1, '2021-01-02', 20.00, OrderTransactionStates::STATE_PAID),
            ],
            'expected' => [
                ['date' => '2021-01-01', 'amount' => 30.00, 'count' => 2],
                ['date' => '2021-01-02', 'amount' => 50.00, 'count' => 3],
            ],
            'since' => '2021-01-01',
            'paid' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function order(int $currencyFactor, string $date, float $price, string $stateId): array
    {
        return [
            'id' => Uuid::randomBytes(),
            'currency_factor' => $currencyFactor,
            'order_date_time' => $date,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'price' => json_encode([
                'netPrice' => $price,
                'taxStatus' => 'gross',
                'totalPrice' => $price,
                'positionPrice' => 1,
            ]),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'state_id' => $stateId,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billing_address_id' => Uuid::randomBytes(),
            'billing_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'shipping_costs' => '{}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }
}
