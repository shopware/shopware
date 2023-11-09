<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @decrecated tag:v6.6.0 - use PaymentException::invalidToken instead
 */
class InvalidTokenException extends PaymentException
{
    public function __construct(
        string $token,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'CHECKOUT__INVALID_PAYMENT_TOKEN',
            'The provided token {{ token }} is invalid and the payment could not be processed.',
            ['token' => $token],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_PAYMENT_TOKEN';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
