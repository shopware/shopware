<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class EmptyCartException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Cart is empty');
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_EMPTY';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
