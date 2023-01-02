<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class BadCredentialsException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Invalid username and/or password.');
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_AUTH_BAD_CREDENTIALS';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
