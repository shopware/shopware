<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class DuplicateWishlistProductException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::DUPLICATE_WISHLIST_PRODUCT,
            'Product already added in wishlist'
        );
    }
}
