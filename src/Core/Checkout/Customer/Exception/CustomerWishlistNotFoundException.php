<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class CustomerWishlistNotFoundException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct(
            'Wishlist for this customer was not found.'
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__WISHLIST_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
