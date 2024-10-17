<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class DependencyInjectionException extends HttpException
{
    public const CART_REDIS_NOT_CONFIGURED = 'CHECKOUT__CART_REDIS_NOT_CONFIGURED';

    public static function redisNotConfiguredForCartStorage(): self
    {
        return new self(
            500,
            self::CART_REDIS_NOT_CONFIGURED,
            // @deprecated tag:v6.7.0 - remove '"shopware.number_range.config.dsn or"' from this message - only "shopware.cart.storage.config.connection" would be supported
            'Parameter "shopware.cart.storage.config.dsn" or "shopware.cart.storage.config.connection" is required for redis storage'
        );
    }
}
