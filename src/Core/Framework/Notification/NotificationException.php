<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Notification;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class NotificationException extends HttpException
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major, as it is unused
     */
    public const WRONG_GATEWAY_CLASS = 'FRAMEWORK__INCREMENT_WRONG_GATEWAY_CLASS';
    public const INVALID_REQUEST_PARAMETER_CODE = 'FRAMEWORK__NOTIFICATION_INVALID_REQUEST_PARAMETER';
    public const API_NOTIFICATION_THROTTLED = 'FRAMEWORK__NOTIFICATION_THROTTLED';

    /**
     * @param class-string<ContextSource> $actual
     */
    public static function invalidAdminSource(string $actual): InvalidContextSourceException
    {
        return new InvalidContextSourceException(AdminApiSource::class, $actual);
    }

    public static function invalidRequestParameter(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_REQUEST_PARAMETER_CODE,
            'The parameter "{{ parameter }}" is invalid.',
            ['parameter' => $name]
        );
    }

    public static function notificationThrottled(int $waitTime, \Throwable $e): self
    {
        return new self(
            Response::HTTP_TOO_MANY_REQUESTS,
            self::API_NOTIFICATION_THROTTLED,
            'Notification throttled for {{ seconds }} seconds.',
            ['seconds' => $waitTime],
            $e
        );
    }
}
