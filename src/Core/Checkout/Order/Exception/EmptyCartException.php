<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class EmptyCartException extends ShopwareHttpException
{
    protected $code = 'CART-EMPTY';

    public function __construct($code = 0, \Throwable $previous = null)
    {
        parent::__construct('Cart is empty', $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
