<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
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
            static::createStub(InitialStateIdLoader::class),
            static::createStub(OrderTransactionStateHandler::class),
            static::createStub(PaymentHandlerRegistry::class),
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectExceptionObject(PaymentException::invalidOrder('foo'));

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
            ->expects($this->once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn(null);

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true, OrderTransactionStates::STATE_FAILED),
            $stateLoader,
            static::createStub(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('bar'));

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
            ->expects($this->once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('supports')
            ->with(PaymentHandlerType::RECURRING, 'bar', Context::createDefaultContext())
            ->willReturn(false);

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($handler);

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true, OrderTransactionStates::STATE_FAILED),
            $stateLoader,
            static::createStub(OrderTransactionStateHandler::class),
            $registry,
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectExceptionObject(PaymentException::paymentTypeUnsupported('bar', PaymentHandlerType::RECURRING));

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
            ->expects($this->once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        $struct = new PaymentTransactionStruct($transaction->getId());

        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('supports')
            ->with(PaymentHandlerType::RECURRING, 'bar', Context::createDefaultContext())
            ->willReturn(true);
        $handler
            ->expects($this->once())
            ->method('recurring')
            ->with($struct, Context::createDefaultContext())
            ->willThrowException(PaymentException::recurringInterrupted($transaction->getId(), 'error_foo'));

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($handler);

        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects($this->once())
            ->method('fail')
            ->with($transaction->getId(), Context::createDefaultContext(), [OrderTransactionStates::STATE_PAID, OrderTransactionStates::STATE_AUTHORIZED]);

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true, OrderTransactionStates::STATE_FAILED),
            $stateLoader,
            $stateHandler,
            $registry,
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectExceptionObject(PaymentException::recurringInterrupted($transaction->getId(), 'error_foo'));

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    /**
     * The payment provider can confirm the payment while the handler is still running and the handler can still
     * fail afterwards. The money was collected, so the caller must not be told the payment failed - otherwise a
     * subscription renewal is marked as failed for a payment that actually went through.
     */
    #[DataProvider('confirmedStateProvider')]
    public function testAPaymentConfirmedWhileTheHandlerRanIsNotReportedAsFailed(string $confirmedState): void
    {
        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects($this->once())
            ->method('fail')
            ->with('foo', Context::createDefaultContext(), [OrderTransactionStates::STATE_PAID, OrderTransactionStates::STATE_AUTHORIZED]);

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true, $confirmedState),
            $this->getInitialStateLoader(),
            $stateHandler,
            $this->getRegistryWithFailingHandler(),
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function confirmedStateProvider(): iterable
    {
        yield 'a captured payment is confirmed' => [OrderTransactionStates::STATE_PAID];
        yield 'an authorized payment is confirmed, even though it may legally be failed' => [OrderTransactionStates::STATE_AUTHORIZED];
    }

    /**
     * When the transaction ended up in a state that can neither be failed nor counts as confirmed, the payment
     * error is the one worth reporting - not the follow-up error about the impossible state transition.
     */
    public function testTheHandlerErrorSurvivesAnImpossibleFailTransition(): void
    {
        $stateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $stateHandler
            ->expects($this->once())
            ->method('fail')
            ->willThrowException(new IllegalTransitionException('cancelled_state_id', 'fail', ['reopen']));

        $processor = new PaymentRecurringProcessor(
            $this->getOrderTransactionRepository(true),
            $this->getInitialStateLoader(),
            $stateHandler,
            $this->getRegistryWithFailingHandler(),
            new PaymentTransactionStructFactory(),
            new NullLogger(),
        );

        $this->expectExceptionObject(PaymentException::recurringInterrupted('foo', 'error_foo'));

        $processor->processRecurring('foo', Context::createDefaultContext());
    }

    private function getInitialStateLoader(): InitialStateIdLoader&MockObject
    {
        $stateLoader = $this->createMock(InitialStateIdLoader::class);
        $stateLoader
            ->expects($this->once())
            ->method('get')
            ->with(OrderTransactionStates::STATE_MACHINE)
            ->willReturn('initial_state_id');

        return $stateLoader;
    }

    private function getRegistryWithFailingHandler(): PaymentHandlerRegistry&MockObject
    {
        $handler = $this->createMock(AbstractPaymentHandler::class);
        $handler
            ->expects($this->once())
            ->method('supports')
            ->with(PaymentHandlerType::RECURRING, 'bar', Context::createDefaultContext())
            ->willReturn(true);
        $handler
            ->expects($this->once())
            ->method('recurring')
            ->willThrowException(PaymentException::recurringInterrupted('foo', 'error_foo'));

        $registry = $this->createMock(PaymentHandlerRegistry::class);
        $registry
            ->expects($this->once())
            ->method('getPaymentMethodHandler')
            ->with('bar')
            ->willReturn($handler);

        return $registry;
    }

    /**
     * @return StaticEntityRepository<OrderTransactionCollection>
     */
    private function getOrderTransactionRepository(bool $returnEntity, ?string $stateAfterFailing = null): StaticEntityRepository
    {
        $entity = new OrderTransactionEntity();
        $entity->setId('foo');
        $entity->setPaymentMethodId('bar');

        $searches = [new OrderTransactionCollection($returnEntity ? [$entity] : [])];

        if ($stateAfterFailing !== null) {
            // Once the processor has tried to fail the transaction it reads it back to find out whether a
            // concurrent confirmation kept it out of the failed state.
            $state = new StateMachineStateEntity();
            $state->setId('state-id');
            $state->setTechnicalName($stateAfterFailing);

            $reread = new OrderTransactionEntity();
            $reread->setId('foo');
            $reread->setPaymentMethodId('bar');
            $reread->setStateMachineState($state);

            $searches[] = new OrderTransactionCollection([$reread]);
        }

        return new StaticEntityRepository($searches);
    }
}
