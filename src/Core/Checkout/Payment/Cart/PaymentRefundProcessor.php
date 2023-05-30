<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\InvalidRefundTransitionException;
use Shopware\Core\Checkout\Payment\Exception\RefundException;
use Shopware\Core\Checkout\Payment\Exception\UnknownRefundException;
use Shopware\Core\Checkout\Payment\Exception\UnknownRefundHandlerException;
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
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry
    ) {
    }

    public function processRefund(string $refundId, Context $context): void
    {
        $result = $this->connection->createQueryBuilder()
            ->select('refund.id', 'state.technical_name', 'transaction.payment_method_id')
            ->from('order_transaction_capture_refund', self::TABLE_ALIAS)
            ->innerJoin(self::TABLE_ALIAS, 'state_machine_state', 'state', 'refund.state_id = state.id')
            ->innerJoin(self::TABLE_ALIAS, 'order_transaction_capture', 'capture', 'capture.id = refund.capture_id')
            ->innerJoin(self::TABLE_ALIAS, 'order_transaction', 'transaction', 'transaction.id = capture.order_transaction_id')
            ->andWhere('refund.id = :refundId')
            ->setParameter('refundId', Uuid::fromHexToBytes($refundId))
            ->executeQuery()
            ->fetchAssociative();

        if (!$result || !\array_key_exists('technical_name', $result) || !\array_key_exists('payment_method_id', $result)) {
            throw new UnknownRefundException($refundId);
        }

        if ($result['technical_name'] !== OrderTransactionCaptureRefundStates::STATE_OPEN) {
            throw new InvalidRefundTransitionException($refundId, $result['technical_name']);
        }

        $paymentMethodId = Uuid::fromBytesToHex($result['payment_method_id']);
        $refundHandler = $this->paymentHandlerRegistry->getRefundPaymentHandler($paymentMethodId);

        if (!$refundHandler instanceof RefundPaymentHandlerInterface) {
            throw new UnknownRefundHandlerException($refundId);
        }

        try {
            $refundHandler->refund($refundId, $context);
        } catch (RefundException $e) {
            $this->stateHandler->fail($refundId, $context);

            throw $e;
        }
    }
}
