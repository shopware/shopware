<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentRecurringProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentRecurringProcessor::class)]
class PaymentRecurringProcessorTest extends TestCase
{
    public function testOrderTransactionNotFoundException(): void
    {
        $order = new OrderEntity();
        $order->setId('foo');

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(false),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(PaymentHandlerRegistry::class),
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The order with id foo is invalid or could not be found.');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testPaymentHandlerNotFoundException(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('foo');
        $paymentMethod->setHandlerIdentifier('foo_recurring_handler');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('foo');
        $transaction->setStateId('initial_state_id');
        $transaction->setPaymentMethodId('foo');
        $transaction->setPaymentMethod($paymentMethod);

        $transactions = new OrderTransactionCollection([$transaction]);

        $order = new OrderEntity();
        $order->setId('foo');
        $order->setTransactions($transactions);

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn(null);

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Could not find payment method with id "bar"');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testPaymentHandlerNotSupportedException(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('foo');
        $paymentMethod->setHandlerIdentifier('foo_recurring_handler');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('foo');
        $transaction->setStateId('initial_state_id');
        $transaction->setPaymentMethodId('foo');
        $transaction->setPaymentMethod($paymentMethod);

        $transactions = new OrderTransactionCollection([$transaction]);

        $order = new OrderEntity();
        $order->setId('foo');
        $order->setTransactions($transactions);

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects(static::once())
            ->method('supports')
            ->with(PaymentHandlerType::RECURRING, 'bar', Context::createDefaultContext())
            ->willReturn(false);

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($handler);

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The payment method with id bar does not support the payment handler type RECURRING.');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testThrowingPaymentHandlerWillSetTransactionStateToFailed(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('foo');
        $paymentMethod->setHandlerIdentifier('foo_recurring_handler');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('foo');
        $transaction->setStateId('initial_state_id');
        $transaction->setPaymentMethodId('foo');
        $transaction->setPaymentMethod($paymentMethod);

        $transactions = new OrderTransactionCollection([$transaction]);

        $order = new OrderEntity();
        $order->setId('foo');
        $order->setTransactions($transactions);

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $struct = new PaymentTransactionStruct($transaction->getId());

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects(static::once())
            ->method('supports')
            ->with(PaymentHandlerType::RECURRING, 'bar', Context::createDefaultContext())
            ->willReturn(true);
        $handler
            ->expects(static::once())
            ->method('recurring')
            ->with($struct, Context::createDefaultContext())
            ->willThrowException(PaymentException::recurringInterrupted($transaction->getId(), 'error_foo'));

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($handler);

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects(static::once())
            ->method('fail')
            ->with($transaction->getId(), Context::createDefaultContext());

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $stateHandler,
            $registry,
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('error_foo');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    private function getOrderTransactionRepository(bool $returnEntity): EntityRepository
    {
        $entity = new OrderTransactionEntity();
        $entity->setId('foo');
        $entity->setPaymentMethodId('bar');

        /** @var StaticEntityRepository<OrderTransactionCollection> $repository */
        $repository = new StaticEntityRepository([
            new OrderTransactionCollection($returnEntity ? [$entity] : []),
        ]);

        return $repository;
    }
}
