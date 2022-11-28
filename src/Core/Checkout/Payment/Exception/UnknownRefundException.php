<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

/**
 * @package checkout
 */
class UnknownRefundException extends RefundProcessException
{
    public function __construct(string $refundId, ?\Throwable $e = null)
    {
        parent::__construct(
            $refundId,
            'The Refund process failed with following exception: Unknown refund with id {{ refundId }}.',
            ['refundId' => $refundId],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__REFUND_UNKNOWN_ERROR';
    }
}
