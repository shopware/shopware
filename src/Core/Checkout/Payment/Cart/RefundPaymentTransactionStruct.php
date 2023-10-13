<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class RefundPaymentTransactionStruct extends PaymentTransactionStruct
{
    public function __construct(
        protected string $refundId,
        string $orderTransactionId,
    ) {
        parent::__construct($orderTransactionId);
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }
}
