<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class InvalidRefundTransitionException extends RefundProcessException
{
    public function __construct(
        string $refundId,
        string $stateName,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            $refundId,
            'The Refund process failed with following exception: Can not process refund with id {{ refundId }} as refund has state {{ stateName }}.',
            ['refundId' => $refundId, 'stateName' => $stateName],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__REFUND_INVALID_TRANSITION_ERROR';
    }
}
