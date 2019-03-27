<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class MissingOrderRelationException extends ShopwareHttpException
{
    public function __construct(string $relation)
    {
        parent::__construct('The required relation "{{ relation }}" is missing .', ['relation' => $relation]);
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CART_MISSING_ORDER_RELATION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
