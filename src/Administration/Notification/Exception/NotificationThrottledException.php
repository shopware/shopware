<?php declare(strict_types=1);

namespace Shopware\Administration\Notification\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - will be removed, use ApiException::notificationThrottled instead
 */
#[Package('administration')]
class NotificationThrottledException extends ShopwareHttpException
{
    public function __construct(
        private readonly int $waitTime,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'Notification throttled for {{ seconds }} seconds.',
            ['seconds' => $this->waitTime],
            $e
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Use ApiException::notificationThrottled instead');

        return 'FRAMEWORK__NOTIFICATION_THROTTLED';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Use ApiException::notificationThrottled instead');

        return Response::HTTP_TOO_MANY_REQUESTS;
    }

    public function getWaitTime(): int
    {
        Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Use ApiException::notificationThrottled instead');

        return $this->waitTime;
    }
}
