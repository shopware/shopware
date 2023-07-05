<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @decrecated tag:v6.6.0 - use PaymentException::unknownPaymentMethod instead
 */
class UnknownPaymentMethodException extends PaymentException
{
    public function __construct(
        string $paymentMethodId,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            'CHECKOUT__UNKNOWN_PAYMENT_METHOD',
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
