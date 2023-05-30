<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class UnknownPaymentMethodException extends ShopwareHttpException
{
    public function __construct(
        string $paymentMethodId,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'The payment method {{ paymentMethodId }} could not be found.',
            ['paymentMethodId' => $paymentMethodId],
            $e
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
