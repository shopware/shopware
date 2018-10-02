<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LineItemNotFoundException extends ShopwareHttpException
{
    protected $code = 'CART-LINE-ITEM-NOT-FOUND';

    public function __construct(string $identifier, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Line item with identifier %s not found', $identifier);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
