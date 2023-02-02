<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class DuplicateWishlistProductException extends ShopwareHttpException
{
    /**
     * @deprecated tag:v6.5.0 - Product id will be removed
     */
    public function __construct(string $productId = '')
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
