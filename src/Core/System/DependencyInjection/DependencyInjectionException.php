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
            'Parameter "shopware.number_range.config.dsn" is required for redis storage'
        );
    }
}
