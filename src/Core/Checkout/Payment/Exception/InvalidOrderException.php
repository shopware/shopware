<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class InvalidOrderException extends ShopwareHttpException
{
    public function __construct(
        string $orderId,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'The order with id {{ orderId }} is invalid or could not be found.',
            ['orderId' => $orderId],
            $e
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
