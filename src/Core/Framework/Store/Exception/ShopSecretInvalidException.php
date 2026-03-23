<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @deprecated tag:v6.8.0 - Will be removed, use StoreException::shopSecretInvalid() instead
 */
class ShopSecretInvalidException extends StoreException
{
    public function __construct()
    {
        parent::__construct(
            Response::HTTP_FORBIDDEN,
            self::STORE_SHOP_SECRET_INVALID,
            'Store shop secret is invalid'
        );
    }
}
