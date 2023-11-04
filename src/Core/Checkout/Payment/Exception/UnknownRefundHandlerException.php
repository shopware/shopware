<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class UnknownRefundHandlerException extends RefundProcessException
{
    public function __construct(
        string $refundId,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            $refundId,
            'The Refund process failed with following exception: Unknown refund handler for refund id {{ refundId }}.',
            ['refundId' => $refundId],
            $e
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__REFUND_UNKNOWN_HANDLER_ERROR';
    }
}
