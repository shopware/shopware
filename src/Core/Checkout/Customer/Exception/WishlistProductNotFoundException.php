<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - will be removed, use CustomerException::wishlistProductNotFound instead
 */
#[Package('customer-order')]
class WishlistProductNotFoundException extends CustomerException
{
    public function __construct(string $productId)
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::WISHLIST_PRODUCT_NOT_FOUND,
            'Wishlist product with id {{ productId }} not found',
            ['productId' => $productId]
        );
    }
}
