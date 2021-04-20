<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class CustomerWishlistNotActivatedException extends ShopwareHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(
            'Wishlist is not activated!',
            [],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__WISHLIST_IS_NOT_ACTIVATED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
