<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidLoginAsCustomerTokenException extends ShopwareHttpException
{
    public function __construct(string $token)
    {
        parent::__construct(
            'The token "{{ token }}" is invalid.',
            ['token' => $token]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__LOGIN_AS_CUSTOMER_INVALID_TOKEN';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
