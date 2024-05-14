<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RecurringPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentRecurringProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\Cart\RecurringPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Event\RecurringPaymentOrderCriteriaEvent;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentRecurringProcessor::class)]
class PaymentRecurringProcessorTest extends TestCase
{
    public function testCorrectCriteriaIsUsed(): void
    {
        $orderId = 'foo';

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('language');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('lineItems');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->with($criteria, Context::createDefaultContext())
            ->willReturn(new EntitySearchResult('order', 0, new OrderCollection(), null, $criteria, Context::createDefaultContext()));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(PaymentHandlerRegistry::class),
            new PaymentTransactionStructFactory(),
            $dispatcher,
        );

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Could not find order with id "foo"');
        $processor->processRecurring($orderId, Context::createDefaultContext());
    }

    public function testOrderNotFoundException(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 0, new OrderCollection(), null, new Criteria(), Context::createDefaultContext()));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(PaymentHandlerRegistry::class),
            new PaymentTransactionStructFactory(),
            $dispatcher,
        );

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Could not find order with id "foo"');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testOrderTransactionNotFoundException(): void
    {
        $order = new OrderEntity();
        $order->setId('foo');

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), Context::createDefaultContext()));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(PaymentHandlerRegistry::class),
            new PaymentTransactionStructFactory(),
            $dispatcher,
        );

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Order with id foo has no transactions.');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testNoInitialStateTransactionsDoesNothing(): void
    {
        $transaction1 = new OrderTransactionEntity();
        $transaction1->setId('foo');
        $transaction1->setStateId('foo');

        $transaction2 = new OrderTransactionEntity();
        $transaction2->setId('bar');
        $transaction2->setStateId('bar');

        $transactions = new OrderTransactionCollection([$transaction1, $transaction2]);

        $order = new OrderEntity();
        $order->setId('foo');
        $order->setTransactions($transactions);

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), Context::createDefaultContext()));

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('some_state_id');

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::never())
            ->method('getRecurringPaymentHandler');

        $registry
            ->expects(static::never())
            ->method('getPaymentMethodHandler');

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects(static::never())
            ->method('fail');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor($repo, $stateLoader, $stateHandler, $registry, new PaymentTransactionStructFactory(), $dispatcher);
        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testTransactionWithoutPaymentMethodThrows(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('foo');
        $transaction->setStateId('initial_state_id');
        $transaction->setPaymentMethodId('foo');

        $transactions = new OrderTransactionCollection([$transaction]);

        $order = new OrderEntity();
        $order->setId('foo');
        $order->setTransactions($transactions);

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), Context::createDefaultContext()));

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(PaymentHandlerRegistry::class),
            new PaymentTransactionStructFactory(),
            $dispatcher,
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Could not find payment method with id "foo"');

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

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), Context::createDefaultContext()));

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getRecurringPaymentHandler')
            ->with('foo')
            ->willReturn(null);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Could not find payment method with handler identifier "foo_recurring_handler"');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testPaymentHandlerCalled(): void
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

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), Context::createDefaultContext()));

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $struct = new RecurringPaymentTransactionStruct($transaction, $order);

        $handler = $this->createMock(RecurringPaymentHandlerInterface::class);
        $handler
            ->expects(static::once())
            ->method('captureRecurring')
            ->with($struct, Context::createDefaultContext());

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getRecurringPaymentHandler')
            ->with('foo')
            ->willReturn($handler);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
        );

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

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(static::once())
            ->method('search')
            ->willReturn(new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), Context::createDefaultContext()));

        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects(static::once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $struct = new RecurringPaymentTransactionStruct($transaction, $order);

        $handler = $this->createMock(RecurringPaymentHandlerInterface::class);
        $handler
            ->expects(static::once())
            ->method('captureRecurring')
            ->with($struct, Context::createDefaultContext())
            ->willThrowException(PaymentException::recurringInterrupted($transaction->getId(), 'error_foo'));

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getRecurringPaymentHandler')
            ->with('foo')
            ->willReturn($handler);

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects(static::once())
            ->method('fail')
            ->with($transaction->getId(), Context::createDefaultContext());

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor($repo, $stateLoader, $stateHandler, $registry, new PaymentTransactionStructFactory(), $dispatcher);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('error_foo');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }
}
