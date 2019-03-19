<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnknownPaymentMethodException extends ShopwareHttpException
{
    public function __construct(string $paymentMethodId)
    {
        parent::__construct(
            'The payment method {{ paymentMethodId }} could not be found.',
            ['paymentMethodId' => $paymentMethodId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__UNKNOWN_PAYMENT_METHOD';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
