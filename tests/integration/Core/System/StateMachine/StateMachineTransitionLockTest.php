<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * Runs against a real database and without the usual test transaction, because the behaviour under test is that
 * one connection waits for a row another connection has locked. A wrapping transaction would keep the fixture
 * invisible to the second connection.
 *
 * @internal
 */
#[Package('checkout')]
class StateMachineTransitionLockTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->ids = new IdsCollection();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('SET SESSION innodb_lock_wait_timeout = DEFAULT');

        // Nothing rolls these writes back, and state_machine_history has no foreign key to the transaction it
        // refers to, so it has to be cleaned up by hand or it leaks into tests that count history entries.
        $this->connection->delete('state_machine_history', ['referenced_id' => Uuid::fromHexToBytes($this->ids->get('transaction'))]);
        $this->connection->delete('`order`', ['id' => Uuid::fromHexToBytes($this->ids->get('10000'))]);
    }

    public function testATransitionTakesTheRowLockBeforeItWritesAnything(): void
    {
        $transactionId = $this->createCommittedOrderTransaction();

        $historyWritten = false;
        $listener = static function () use (&$historyWritten): void {
            $historyWritten = true;
        };
        static::getContainer()->get('event_dispatcher')->addListener('state_machine_history.written', $listener);

        $otherConnection = DriverManager::getConnection($this->connection->getParams());
        $otherConnection->beginTransaction();
        $otherConnection->fetchOne(
            'SELECT `state_id` FROM `order_transaction` WHERE `id` = :id FOR UPDATE',
            ['id' => Uuid::fromHexToBytes($transactionId)]
        );

        // Give up quickly instead of sitting out the server default, the point is that it waits at all
        $this->connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            static::getContainer()->get(OrderTransactionStateHandler::class)
                ->paid($transactionId, Context::createDefaultContext());

            static::fail('the transition should have waited for the row lock held by the other connection');
        } catch (LockWaitTimeoutException) {
            // The transition asked the database for the row and had to queue behind the other connection,
            // which is what keeps two processes from computing a transition from the same state.
        } finally {
            $otherConnection->rollBack();
            $otherConnection->close();
            static::getContainer()->get('event_dispatcher')->removeListener('state_machine_history.written', $listener);
        }

        // The lock has to be taken before the transition writes, not after. A transition that records where it is
        // going and only then waits for the row has already computed that destination from an unguarded read.
        static::assertFalse($historyWritten, 'the transition wrote its history entry before it held the row lock');

        static::assertSame(
            OrderTransactionStates::STATE_OPEN,
            $this->fetchStateName($transactionId),
            'a transition that could not take the lock must not have written anything'
        );
    }

    private function createCommittedOrderTransaction(): string
    {
        $transaction = (new OrderTransactionBuilder($this->ids, 'transaction', state: OrderTransactionStates::STATE_OPEN))
            ->build();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->upsert([$order], Context::createDefaultContext());

        return $this->ids->get('transaction');
    }

    private function fetchStateName(string $transactionId): string
    {
        $name = $this->connection->fetchOne(
            'SELECT `state_machine_state`.`technical_name`
             FROM `order_transaction`
             INNER JOIN `state_machine_state` ON `state_machine_state`.`id` = `order_transaction`.`state_id`
             WHERE `order_transaction`.`id` = :id',
            ['id' => Uuid::fromHexToBytes($transactionId)]
        );

        static::assertIsString($name);

        return $name;
    }
}
