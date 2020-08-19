<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class PaymentRefundHandlerNotFoundException extends ShopwareHttpException
{
    public function __construct(string $refundHandlerIdentifier)
    {
        parent::__construct(
            'The payment refund handler for identifier {{ refundHandlerIdentifier }} could not be found.',
            ['refundHandlerIdentifier' => $refundHandlerIdentifier]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PAYMENT_REFUND_HANDLER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
