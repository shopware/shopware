<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class CustomerWishlistNotFoundException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            self::WISHLIST_NOT_FOUND,
            'Wishlist for this customer was not found.'
        );
    }
}
