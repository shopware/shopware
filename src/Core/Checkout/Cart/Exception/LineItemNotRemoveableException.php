<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LineItemNotRemoveableException extends ShopwareHttpException
{
    protected $code = 'CART-LINE-ITEM-NOT-REMOVEABLE';

    public function __construct(string $identifier, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Line item with identifier %s cannot be removed', $identifier);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
