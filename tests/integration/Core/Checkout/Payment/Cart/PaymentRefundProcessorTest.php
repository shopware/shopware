<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStructFactory;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\Test\Integration\Builder\Order\OrderBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionCaptureBuilder;
use Shopware\Core\Test\Integration\Builder\Order\OrderTransactionCaptureRefundBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentRefundProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private PaymentRefundProcessor $paymentRefundProcessor;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->paymentRefundProcessor = static::getContainer()->get(PaymentRefundProcessor::class);
    }

    public function testItThrowsIfRefundNotFound(): void
    {
        // capture has no refund
        $capture = (new OrderTransactionCaptureBuilder($this->ids, 'capture', $this->ids->get('transaction')))
            ->build();

        $transaction = (new OrderTransactionBuilder($this->ids, 'transaction'))
            ->addCapture('capture', $capture)
            ->build();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->upsert([$order], Context::createDefaultContext());

        $this->expectExceptionObject(PaymentException::unknownRefund($this->ids->get('refund')));

        $this->paymentRefundProcessor->processRefund($this->ids->get('refund'), Context::createDefaultContext());
    }

    public function testItThrowsOnNotAvailableHandler(): void
    {
        $refund = (new OrderTransactionCaptureRefundBuilder(
            $this->ids,
            'refund',
            $this->ids->get('capture')
        ))
            ->add('stateId', $this->getStateMachineState(
                OrderTransactionCaptureRefundStates::STATE_MACHINE,
                OrderTransactionCaptureRefundStates::STATE_OPEN
            ))
            ->build();

        $capture = (new OrderTransactionCaptureBuilder($this->ids, 'capture', $this->ids->get('transaction')))
            ->addRefund('refund', $refund)
            ->build();

        $transaction = (new OrderTransactionBuilder($this->ids, '10000'))
            ->addCapture('capture', $capture)
            ->add('paymentMethod', [
                'id' => $this->ids->get('payment_method'),
                // this enables refund handling for the payment method
                'technicalName' => 'payment_test',
                'handlerIdentifier' => AbstractPaymentHandler::class,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'foo',
                    ],
                ],
            ])
            ->build();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->upsert([$order], Context::createDefaultContext());

        $this->expectExceptionObject(PaymentException::unknownRefundHandler($this->ids->get('refund')));

        $this->paymentRefundProcessor->processRefund($this->ids->get('refund'), Context::createDefaultContext());
    }

    #[DataProvider('getInvalidStatesForTransitions')]
    public function testItThrowsIfRefundIsInWrongState(string $stateMachineState): void
    {
        $refund = (new OrderTransactionCaptureRefundBuilder(
            $this->ids,
            'refund',
            $this->ids->get('capture')
        ))
            ->add('stateId', $this->getStateMachineState(
                OrderTransactionCaptureRefundStates::STATE_MACHINE,
                $stateMachineState
            ))
            ->build();

        $capture = (new OrderTransactionCaptureBuilder($this->ids, 'capture', $this->ids->get('transaction')))
            ->addRefund('refund', $refund)
            ->build();

        $transaction = (new OrderTransactionBuilder($this->ids, 'transaction'))
            ->addCapture('capture', $capture)
            ->build();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->upsert([$order], Context::createDefaultContext());

        $this->expectExceptionObject(PaymentException::refundInvalidTransition($refund['id'], $stateMachineState));

        $this->paymentRefundProcessor->processRefund($this->ids->get('refund'), Context::createDefaultContext());
    }

    public function testItCallsRefundHandler(): void
    {
        $handlerMock = $this->createMock(AbstractPaymentHandler::class);
        $handlerMock
            ->expects($this->once())
            ->method('refund');

        $handlerMock
            ->expects($this->once())
            ->method('supports')
            ->with(PaymentHandlerType::REFUND, $this->ids->get('payment_method'), Context::createDefaultContext())
            ->willReturn(true);

        $handlerRegistryMock = $this->createMock(PaymentHandlerRegistry::class);
        $handlerRegistryMock
            ->method('getPaymentMethodHandler')
            ->willReturn($handlerMock);

        $processor = new PaymentRefundProcessor(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(OrderTransactionCaptureRefundStateHandler::class),
            $handlerRegistryMock,
            static::getContainer()->get(PaymentTransactionStructFactory::class),
        );

        $refund = (new OrderTransactionCaptureRefundBuilder(
            $this->ids,
            'refund',
            $this->ids->get('capture')
        ))
            ->add('stateId', $this->getStateMachineState(
                OrderTransactionCaptureRefundStates::STATE_MACHINE,
                OrderTransactionCaptureRefundStates::STATE_OPEN
            ))
            ->build();

        $capture = (new OrderTransactionCaptureBuilder($this->ids, 'capture', $this->ids->get('transaction')))
            ->addRefund('refund', $refund)
            ->build();

        $transaction = (new OrderTransactionBuilder($this->ids, '10000'))
            ->addCapture('capture', $capture)
            ->add('paymentMethod', [
                'id' => $this->ids->get('payment_method'),
                // this enables refund handling for the payment method
                'technicalName' => 'payment_test',
                'handlerIdentifier' => AbstractPaymentHandler::class,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'foo',
                    ],
                ],
            ])
            ->build();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->upsert([$order], Context::createDefaultContext());

        $processor->processRefund(
            $this->ids->get('refund'),
            Context::createDefaultContext()
        );
    }

    /**
     * The refund can reach a state that cannot be failed while the handler is running, for example because it
     * completed concurrently. The refund error must still be the one that reaches the caller.
     */
    public function testTheRefundErrorSurvivesAnImpossibleFailTransition(): void
    {
        $handlerMock = $this->createMock(AbstractPaymentHandler::class);
        $handlerMock->method('supports')->willReturn(true);
        $handlerMock->expects($this->once())
            ->method('refund')
            ->willThrowException(PaymentException::refundInterrupted($this->ids->get('refund'), 'handler said no'));

        $handlerRegistryMock = $this->createMock(PaymentHandlerRegistry::class);
        $handlerRegistryMock->method('getPaymentMethodHandler')->willReturn($handlerMock);

        $stateHandler = $this->createMock(OrderTransactionCaptureRefundStateHandler::class);
        $stateHandler->expects($this->once())
            ->method('fail')
            ->willThrowException(new IllegalTransitionException('completed-state-id', 'fail', ['reopen']));

        $processor = new PaymentRefundProcessor(
            static::getContainer()->get(Connection::class),
            $stateHandler,
            $handlerRegistryMock,
            static::getContainer()->get(PaymentTransactionStructFactory::class),
        );

        $this->createOpenRefund();

        $this->expectExceptionObject(
            PaymentException::refundInterrupted($this->ids->get('refund'), 'handler said no')
        );

        $processor->processRefund($this->ids->get('refund'), Context::createDefaultContext());
    }

    /**
     * @return iterable<array<int, string>>
     */
    public static function getInvalidStatesForTransitions(): iterable
    {
        yield [OrderTransactionCaptureRefundStates::STATE_CANCELLED];
        yield [OrderTransactionCaptureRefundStates::STATE_COMPLETED];
        yield [OrderTransactionCaptureRefundStates::STATE_FAILED];
        yield [OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS];
    }

    private function createOpenRefund(): void
    {
        $refund = (new OrderTransactionCaptureRefundBuilder($this->ids, 'refund', $this->ids->get('capture')))
            ->add('stateId', $this->getStateMachineState(
                OrderTransactionCaptureRefundStates::STATE_MACHINE,
                OrderTransactionCaptureRefundStates::STATE_OPEN
            ))
            ->build();

        $capture = (new OrderTransactionCaptureBuilder($this->ids, 'capture', $this->ids->get('transaction')))
            ->addRefund('refund', $refund)
            ->build();

        $transaction = (new OrderTransactionBuilder($this->ids, '10000'))
            ->addCapture('capture', $capture)
            ->add('paymentMethod', [
                'id' => $this->ids->get('payment_method'),
                'technicalName' => 'payment_test',
                'handlerIdentifier' => AbstractPaymentHandler::class,
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'foo',
                    ],
                ],
            ])
            ->build();

        $order = (new OrderBuilder($this->ids, '10000'))
            ->addTransaction('transaction', $transaction)
            ->build();

        $this->orderRepository->upsert([$order], Context::createDefaultContext());
    }
}
