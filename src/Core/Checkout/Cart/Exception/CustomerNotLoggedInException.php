<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CustomerNotLoggedInException extends ShopwareHttpException
{
    protected $code = 'CUSTOMER-NOT-LOGGED-IN';

    public function __construct($code = 0, \Throwable $previous = null)
    {
        parent::__construct('Customer is not logged in', $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
