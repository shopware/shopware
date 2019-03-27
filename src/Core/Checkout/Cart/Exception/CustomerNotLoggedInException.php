<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CustomerNotLoggedInException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Customer is not logged in.');
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_NOT_LOGGED_IN';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
