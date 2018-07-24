<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LineItemCoverNotFoundException extends ShopwareHttpException
{
    protected $code = 'CART-LINE-ITEM-COVER-NOT-FOUND';

    public function __construct(string $coverId, string $lineItemKey, int $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Line item cover with identifier `%s` for line item `%s` not found', $coverId, $lineItemKey);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
