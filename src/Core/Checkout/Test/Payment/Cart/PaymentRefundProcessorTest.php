<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Payment\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Checkout\Payment\Exception\InvalidRefundTransitionException;
use Shopware\Core\Checkout\Payment\Exception\UnknownRefundException;
use Shopware\Core\Checkout\Payment\Exception\UnknownRefundHandlerException;
use Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransaction\OrderTransactionBuilder;
use Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureBuilder;
use Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundBuilder;
use Shopware\Core\Checkout\Test\Order\OrderBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentRefundProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private EntityRepository $orderRepository;

    private PaymentRefundProcessor $paymentRefundProcessor;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->paymentRefundProcessor = $this->getContainer()->get(PaymentRefundProcessor::class);
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

        static::expectException(UnknownRefundException::class);

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
                'handlerIdentifier' => RefundPaymentHandlerInterface::class,
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

        static::expectException(UnknownRefundHandlerException::class);

        $this->paymentRefundProcessor->processRefund($this->ids->get('refund'), Context::createDefaultContext());
    }

    /**
     * @dataProvider getInvalidStatesForTransitions
     */
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

        static::expectException(InvalidRefundTransitionException::class);

        $this->paymentRefundProcessor->processRefund($this->ids->get('refund'), Context::createDefaultContext());
    }

    public function testItCallsRefundHandler(): void
    {
        $handlerMock = $this->createMock(RefundPaymentHandlerInterface::class);
        $handlerMock
            ->expects(static::once())
            ->method('refund');

        $handlerRegistryMock = $this->createMock(PaymentHandlerRegistry::class);
        $handlerRegistryMock
            ->method('getRefundPaymentHandler')
            ->willReturn($handlerMock);

        $processor = new PaymentRefundProcessor(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(OrderTransactionCaptureRefundStateHandler::class),
            $handlerRegistryMock
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
                'handlerIdentifier' => RefundPaymentHandlerInterface::class,
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

    public static function getInvalidStatesForTransitions(): iterable
    {
        yield [OrderTransactionCaptureRefundStates::STATE_CANCELLED];
        yield [OrderTransactionCaptureRefundStates::STATE_COMPLETED];
        yield [OrderTransactionCaptureRefundStates::STATE_FAILED];
        yield [OrderTransactionCaptureRefundStates::STATE_IN_PROGRESS];
    }
}
