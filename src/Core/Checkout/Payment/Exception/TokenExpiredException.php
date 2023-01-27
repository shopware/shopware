<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class TokenExpiredException extends ShopwareHttpException
{
    public function __construct(
        string $token,
        ?\Throwable $e = null
    ) {
        parent::__construct(
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
