<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @decrecated tag:v6.6.0 - use PaymentException::syncProcessInterrupted instead
 */
class TokenExpiredException extends PaymentException
{
    public function __construct(
        string $token,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_GONE,
            'CHECKOUT__PAYMENT_TOKEN_EXPIRED',
            'The provided token {{ token }} is expired and the payment could not be processed.',
            ['token' => $token],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PAYMENT_TOKEN_EXPIRED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_GONE;
    }
}
