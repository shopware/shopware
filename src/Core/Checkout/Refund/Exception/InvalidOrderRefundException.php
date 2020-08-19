<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidOrderRefundException extends ShopwareHttpException
{
    public function __construct(string $orderRefundId)
    {
        parent::__construct(
            'The order refund with id {{ orderRefundId }} is invalid or could not be found.',
            ['orderRefundId' => $orderRefundId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_ORDER_REFUND_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
