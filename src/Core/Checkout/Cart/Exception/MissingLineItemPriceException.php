<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MissingLineItemPriceException extends ShopwareHttpException
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            'Line item {{ identifier }} contains no price definition or already calculated price.',
            ['identifier' => $identifier]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_MISSING_PRICE_DEFINITION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
