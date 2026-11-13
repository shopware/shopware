<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Event;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderTransactionStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderTransactionStateMachineStateChangeEvent::class)]
class OrderTransactionStateMachineStateChangeEventTest extends TestCase
{
    public function testFlowsGetTheTransactionAndWebhooksOnlyItsId(): void
    {
        $definition = (new BusinessEventCollector(
            new BusinessEventRegistry(),
            new EventDispatcher(),
            static::createStub(Connection::class)
        ))->define(OrderTransactionStateMachineStateChangeEvent::class, 'state_enter.order_transaction.state.paid');

        static::assertNotNull($definition);
        static::assertSame(
            [
                OrderAware::ORDER => (new EntityType(OrderDefinition::class))->toArray(),
                OrderTransactionAware::ORDER_TRANSACTION => [
                    ...(new EntityType(OrderTransactionDefinition::class))->toArray(),
                    EventDataCollection::HIDDEN_FROM_WEBHOOK => true,
                ],
                OrderTransactionAware::ORDER_TRANSACTION_ID => (new ScalarValueType(ScalarValueType::TYPE_STRING))->toArray(),
            ],
            $definition->getData()
        );
        static::assertTrue($definition->getAware(OrderTransactionAware::class));
        static::assertTrue($definition->getAware(ScalarValuesAware::class));
    }

    public function testExposesTheChangedTransaction(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('order-transaction-id');

        $event = new OrderTransactionStateMachineStateChangeEvent(
            'state_enter.order_transaction.state.paid',
            new OrderEntity(),
            $transaction,
            Context::createDefaultContext()
        );

        static::assertSame($transaction, $event->getOrderTransaction());
        static::assertSame('order-transaction-id', $event->getOrderTransactionId());
        static::assertSame([OrderTransactionAware::ORDER_TRANSACTION_ID => 'order-transaction-id'], $event->getValues());
        static::assertSame('state_enter.order_transaction.state.paid', $event->getName());
    }
}
