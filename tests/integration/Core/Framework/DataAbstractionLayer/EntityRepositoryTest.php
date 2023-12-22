<?php

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class EntityRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * @param array<array{payment: string, state:string}> $transactions
     */
    #[DataProvider('oneToManyFilterProvider')]
    public function testOneToManyFilter(array $transactions, Criteria $criteria, bool $match)
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

        static::assertCount($match ? 1 : 0, $found->getIds());
    }

    public static function oneToManyFilterProvider()
    {
        $ids = new IdsCollection();

        yield 'Matching by just check for payment method' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal'))),
            true
        ];

        yield 'Multi filter on transactions works, matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.amount', 100)
                ])),
            true
        ];

        yield 'Multi filter on transactions works, not matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.amount', 101)
                ])),
            false
        ];


        yield 'Match exact state of payment method' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'open')
                ])),
            true
        ];

        yield 'Payment method exists but state is not matching' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'paid')
                ])),
            false
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
                            new EqualsFilter('transactions.stateMachineState.technicalName', 'open')
                        ]),
                        new EqualsFilter('transactions.stateMachineState.technicalName', 'paid')
                    ]),
                ),
            true
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
                            new EqualsFilter('transactions.stateMachineState.technicalName', 'open')
                        ]),
                        new EqualsFilter('transactions.stateMachineState.technicalName', 'paid')
                    ]),
                ),
            false
        ];
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
            'amount' => 100,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

}
