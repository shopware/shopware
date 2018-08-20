<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidQuantityException extends ShopwareHttpException
{
    protected $code = 'CART-INVALID-QUANTITY';

    public function __construct(int $quantity, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The quantity must be a positive integer. Given: "%s" ', $quantity);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
