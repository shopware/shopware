<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LineItemTypeNotSupportedException extends ShopwareHttpException
{
    public function __construct(string $type)
    {
        parent::__construct(sprintf('LineItem with type is not supported %s', $type), [], null);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return 'CART__LINE_ITEM_NOT_SUPPORTED';
    }
}
