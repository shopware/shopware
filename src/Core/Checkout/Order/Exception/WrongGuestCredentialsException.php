<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class WrongGuestCredentialsException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Wrong credentials for guest authentication.');
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__GUEST_WRONG_CREDENTIALS';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
