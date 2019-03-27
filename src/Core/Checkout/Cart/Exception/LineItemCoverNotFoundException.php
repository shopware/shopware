<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class LineItemCoverNotFoundException extends ShopwareHttpException
{
    public function __construct(string $coverId, string $lineItemKey)
    {
        parent::__construct(
            'Line item cover with identifier "{{ lineItemId }}" for line item "{{ coverId }}" not found',
            ['coverId' => $coverId, 'lineItemId' => $lineItemKey]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_LINEITEM_COVER_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
