<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderPaymentMethodNotChangeable extends ShopwareHttpException
{
    public function __construct(?\Throwable $e = null)
    {
        parent::__construct(
            'Payment methods of order with current payment transaction type can not be changed.',
            [],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_PAYMENT_METHOD_NOT_CHANGEABLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
