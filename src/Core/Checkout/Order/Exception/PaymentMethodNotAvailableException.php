<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class PaymentMethodNotAvailableException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'The order has no active payment method - {{ id }}',
            ['id' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__UNAVAILABLE_PAYMENT_METHOD';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
