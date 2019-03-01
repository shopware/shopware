<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LineItemNotStackableException extends ShopwareHttpException
{
    protected $code = 'CART-LINE-ITEM-NOT-STACKABLE';

    public function __construct(string $identifier, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('Line item with identifier %s is not stackable and the quantity cannot be changed', $identifier);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
