<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidChildQuantityException extends ShopwareHttpException
{
    protected $code = 'CART-INVALID-CHILD-QUANTITY';

    public function __construct(int $childQuantity, int $parentQuantity, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'The quantity of a child (`%s`) must be a multiple of the parent quantity (`%s`)',
            $childQuantity,
            $parentQuantity
        );

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
