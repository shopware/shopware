<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\RefundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class RefundPayload implements PaymentPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;
    use RemoveAppTrait;

    protected Source $source;

    protected OrderTransactionCaptureRefundEntity $refund;

    public function __construct(
        OrderTransactionCaptureRefundEntity $refund,
        protected OrderEntity $order
    ) {
        if ($refund->getTransactionCapture() && $refund->getTransactionCapture()->getTransaction()) {
            $transaction = $this->removeApp($refund->getTransactionCapture()->getTransaction());
            $refund->getTransactionCapture()->setTransaction($transaction);
        }

        $this->refund = $refund;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        if ($this->refund->getTransactionCapture() && $this->refund->getTransactionCapture()->getTransaction()) {
            return $this->refund->getTransactionCapture()->getTransaction();
        }

        throw new RefundException($this->refund->getId(), 'No transaction found for refund.');
    }

    public function getRefund(): OrderTransactionCaptureRefundEntity
    {
        return $this->refund;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
