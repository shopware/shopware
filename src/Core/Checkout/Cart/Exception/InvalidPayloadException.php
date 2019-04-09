<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidPayloadException extends ShopwareHttpException
{
    public function __construct(string $key, string $lineItemId)
    {
        parent::__construct(
            'Unable to save payload with key `{{ key }}` on line item `{{ lineItemId }}`. Only scalar data types are allowed.',
            ['key' => $key, 'lineItemId' => $lineItemId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_INVALID_LINEITEM_PAYLOAD';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
