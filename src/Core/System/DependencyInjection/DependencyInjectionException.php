<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class DependencyInjectionException extends HttpException
{
    public const NUMBER_RANGE_REDIS_NOT_CONFIGURED = 'SYSTEM__NUMBER_RANGE_REDIS_NOT_CONFIGURED';

    public static function redisNotConfiguredForNumberRangeIncrementer(): self
    {
        return new self(
            500,
            self::NUMBER_RANGE_REDIS_NOT_CONFIGURED,
            // @deprecated tag:v6.7.0 - remove '"shopware.number_range.config.dsn" or' from this message - only "shopware.number_range.config.connection" would be supported
            'Parameter "shopware.number_range.config.dsn" or "shopware.number_range.config.connection" is required for redis storage'
        );
    }
}
