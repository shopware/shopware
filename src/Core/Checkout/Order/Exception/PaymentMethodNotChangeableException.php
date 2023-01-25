<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class PaymentMethodNotChangeableException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'The order has an active transaction - {{ id }}',
            ['id' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PAYMENT_METHOD_UNCHANGEABLE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
