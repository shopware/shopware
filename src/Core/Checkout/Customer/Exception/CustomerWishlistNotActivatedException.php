<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerWishlistNotActivatedException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'Wishlist is not activated!'
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
