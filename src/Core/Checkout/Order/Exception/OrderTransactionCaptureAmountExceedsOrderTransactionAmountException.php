<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderTransactionCaptureAmountExceedsOrderTransactionAmountException extends ShopwareHttpException
{
    public function __construct(float $orderTransactionCaptureAmount, float $orderTransactionAmount)
    {
        parent::__construct(
            'Capture amount {{ orderTransactionCaptureAmount }} exceeds the order transactions amount of {{ orderTransactionAmount }}',
            [
                'orderTransactionCaptureAmount' => $orderTransactionCaptureAmount,
                'orderTransactionAmount' => $orderTransactionAmount,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_TRANSACTION_CAPTURE_AMOUNT_EXCEEDS_ORDER_TRANSACTION_AMOUNT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
