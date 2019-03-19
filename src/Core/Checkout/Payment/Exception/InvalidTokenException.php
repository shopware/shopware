<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidTokenException extends ShopwareHttpException
{
    public function __construct(string $token)
    {
        parent::__construct(
            'The provided token {{ token }} is invalid and the payment could not be processed.',
            ['token' => $token]
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
