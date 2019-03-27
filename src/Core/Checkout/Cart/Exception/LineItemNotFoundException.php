<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LineItemNotFoundException extends ShopwareHttpException
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            'Line item with identifier {{ identifier }} not found.',
            ['identifier' => $identifier]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_LINEITEM_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
