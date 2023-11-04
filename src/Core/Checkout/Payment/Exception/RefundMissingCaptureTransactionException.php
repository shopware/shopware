<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class RefundMissingCaptureTransactionException extends PaymentProcessException
{
    public function __construct(
        string $refundId,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            $refundId,
            'The refund with id {{ refundId }} has no capture transaction associated',
            ['refundId' => $refundId],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PAYMENT_REFUND_MISSING_CAPTURE_TRANSACTION';
    }
}
