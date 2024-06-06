<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RecurringPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentRecurringProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentRecurringProcessor::class)]
class PaymentRecurringProcessorTest extends TestCase
{
    /**
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    public function testCorrectCriteriaIsUsed(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

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

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($this->createMock(RecurringPaymentHandlerInterface::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $this->getOrderTransactionRepository(true),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
            new NullLogger(),
        );

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Could not find order with id "foo"');
        $processor->processRecurring($orderId, Context::createDefaultContext());
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    public function testOldOrderNotFoundException(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

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

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects(static::once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($this->createMock(RecurringPaymentHandlerInterface::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $this->getOrderTransactionRepository(true),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
            new NullLogger(),
        );

        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('Could not find order with id "foo"');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    public function testOrderTransactionNotFoundException(): void
    {
        $order = new OrderEntity();
        $order->setId('foo');

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo->expects(static::never())->method('search');

        $processor = new PaymentRecurringProcessor(
            $orderRepo,
            $this->getOrderTransactionRepository(false),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(OrderTransactionStateHandler::class),
            $this->createMock(PaymentHandlerRegistry::class),
            new PaymentTransactionStructFactory(),
            $this->createMock(EventDispatcherInterface::class),
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

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo->expects(static::never())->method('search');

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

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(static::never())->method('dispatch');

        $processor = new PaymentRecurringProcessor(
            $orderRepo,
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
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

        $orderRepo = $this->createMock(EntityRepository::class);
        $orderRepo->expects(static::never())->method('search');

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

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(static::never())->method('dispatch');

        $processor = new PaymentRecurringProcessor(
            $orderRepo,
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
            new NullLogger(),
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The payment method with id bar does not support the payment handler type RECURRING.');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    public function testOldPaymentHandlerCalled(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

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
            ->expects(static::exactly(2))
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
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($handler);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RecurringPaymentOrderCriteriaEvent::class));

        $processor = new PaymentRecurringProcessor(
            $repo,
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $this->createMock(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
            new NullLogger(),
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

        /** @var StaticEntityRepository<OrderCollection> $orderRepository */
        $orderRepository = new StaticEntityRepository([]);

        $processor = new PaymentRecurringProcessor(
            $orderRepository,
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $stateHandler,
            $registry,
            new PaymentTransactionStructFactory(),
            $this->createMock(EventDispatcherInterface::class),
            new NullLogger(),
        );

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('error_foo');

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed with old payment handler interfaces
     */
    public function testOldThrowingPaymentHandlerWillSetTransactionStateToFailed(): void
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
            ->expects(static::exactly(2))
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
            ->method('getPaymentMethodHandler')
            ->with('bar')
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

        $processor = new PaymentRecurringProcessor(
            $repo,
            $this->getOrderTransactionRepository(true),
            $stateLoader,
            $stateHandler,
            $registry,
            new PaymentTransactionStructFactory(),
            $dispatcher,
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
