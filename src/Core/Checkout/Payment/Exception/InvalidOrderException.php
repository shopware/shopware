<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidOrderException extends ShopwareHttpException
{
    public function __construct(string $orderId)
    {
        parent::__construct(
            'The order with id {{ orderId }} is invalid or could not be found.',
            ['orderId' => $orderId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_ORDER_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
