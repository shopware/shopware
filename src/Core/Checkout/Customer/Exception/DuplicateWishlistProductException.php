<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('customer-order')]
class DuplicateWishlistProductException extends ShopwareHttpException
{
    public function __construct()
    {
        parent::__construct('Product already added in wishlist');
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__DUPLICATE_WISHLIST_PRODUCT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
