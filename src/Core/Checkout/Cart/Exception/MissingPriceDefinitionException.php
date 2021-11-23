<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MissingPriceDefinitionException extends ShopwareHttpException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'Missing price definition'
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_MISSING_PRICE_VALUE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
