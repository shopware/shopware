<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Notification;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class NotificationException extends HttpException
{
    public const WRONG_GATEWAY_CLASS = 'FRAMEWORK__INCREMENT_WRONG_GATEWAY_CLASS';

    /**
     * @param class-string<ContextSource> $actual
     */
    public static function invalidAdminSource(string $actual): InvalidContextSourceException
    {
        return new InvalidContextSourceException(AdminApiSource::class, $actual);
    }
}
