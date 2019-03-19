<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidChildQuantityException extends ShopwareHttpException
{
    public function __construct(int $childQuantity, int $parentQuantity)
    {
        parent::__construct(
            'The quantity of a child "{{ childQuantity }}" must be a multiple of the parent quantity "{{ parentQuantity }}"',
            ['childQuantity' => $childQuantity, 'parentQuantity' => $parentQuantity]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_INVALID_CHILD_QUANTITY';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
