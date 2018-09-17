<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MixedLineItemTypeException extends ShopwareHttpException
{
    protected $code = 'CART-MIXED-LINE-ITEM-TYPE';

    public function __construct(string $key, string $type, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Line item with key %s already exists with different type %s', $key, $type);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
