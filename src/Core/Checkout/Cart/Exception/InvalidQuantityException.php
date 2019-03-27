<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidQuantityException extends ShopwareHttpException
{
    public function __construct(int $quantity)
    {
        parent::__construct(
            'The quantity must be a positive integer. Given: "{{ quantity }}"',
            ['quantity' => $quantity]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_INVALID_LINEITEM_QUANTITY';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
