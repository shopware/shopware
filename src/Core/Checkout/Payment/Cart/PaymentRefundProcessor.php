<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
class PaymentRefundProcessor
{
    private const TABLE_ALIAS = 'refund';

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly OrderTransactionCaptureRefundStateHandler $stateHandler,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly AbstractPaymentTransactionStructFactory $transactionStructFactory,
    ) {
    }

    public function processRefund(string $refundId, Context $context): void
    {
        $result = $this->connection->createQueryBuilder()
            ->select('refund.id', 'state.technical_name', 'transaction.payment_method_id', 'transaction.id as transaction_id')
            ->from('order_transaction_capture_refund', self::TABLE_ALIAS)
            ->innerJoin(self::TABLE_ALIAS, 'state_machine_state', 'state', 'refund.state_id = state.id')
            ->innerJoin(self::TABLE_ALIAS, 'order_transaction_capture', 'capture', 'capture.id = refund.capture_id')
            ->innerJoin(self::TABLE_ALIAS, 'order_transaction', 'transaction', 'transaction.id = capture.order_transaction_id')
            ->andWhere('refund.id = :refundId')
            ->setParameter('refundId', Uuid::fromHexToBytes($refundId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$result || !\array_key_exists('technical_name', $result) || !\array_key_exists('payment_method_id', $result)) {
            throw PaymentException::unknownRefund($refundId);
        }

        if ($result['technical_name'] !== OrderTransactionCaptureRefundStates::STATE_OPEN) {
            throw PaymentException::refundInvalidTransition($refundId, $result['technical_name']);
        }

        $orderTransactionId = Uuid::fromBytesToHex($result['transaction_id']);
        $paymentMethodId = Uuid::fromBytesToHex($result['payment_method_id']);
        $refundHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($paymentMethodId);

        if ($refundHandler instanceof RefundPaymentHandlerInterface) {
            try {
                $refundHandler->refund($refundId, $context);

                return;
            } catch (PaymentException $e) {
                $this->stateHandler->fail($refundId, $context);

                throw $e;
            }
        }

        if (!$refundHandler instanceof AbstractPaymentHandler || !$refundHandler->supports(PaymentHandlerType::REFUND, $paymentMethodId, $context)) {
            throw PaymentException::unknownRefundHandler($refundId);
        }

        try {
            $struct = $this->transactionStructFactory->refund($refundId, $orderTransactionId);
            $refundHandler->refund($struct, $context);
        } catch (\Throwable $e) {
            $this->stateHandler->fail($refundId, $context);

            throw $e;
        }
    }
}
