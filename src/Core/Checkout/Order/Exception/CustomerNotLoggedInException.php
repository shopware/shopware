<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CustomerNotLoggedInException extends ShopwareHttpException
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct('Customer is not logged in.', 4005, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
