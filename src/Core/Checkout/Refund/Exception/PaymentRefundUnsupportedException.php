<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class PaymentRefundUnsupportedException extends ShopwareHttpException
{
    public function __construct(string $paymentMethodId)
    {
        parent::__construct(
            'The payment method {{ paymentMethodId }} does not support refunds.',
            ['paymentMethodId' => $paymentMethodId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PAYMENT_REFUND_UNSUPPORTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
