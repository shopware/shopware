<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MixedLineItemTypeException extends ShopwareHttpException
{
    public function __construct(string $key, string $type)
    {
        parent::__construct(
            'Line item with key {{ identifier }} already exists with different type {{ type }}.',
            ['identifier' => $key, 'type' => $type]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_MIXED_LINEITEM_TYPE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
