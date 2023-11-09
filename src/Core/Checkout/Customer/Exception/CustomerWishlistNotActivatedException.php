<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Exception;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - reason:remove-exception - will be removed, use CustomerException::customerWishlistNotActivated instead
 */
#[Package('checkout')]
class CustomerWishlistNotActivatedException extends CustomerException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_FORBIDDEN,
            self::WISHLIST_IS_NOT_ACTIVATED,
            'Wishlist is not activated!'
        );
    }
}
