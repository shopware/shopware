<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Notification\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Notification\Exception\NotificationThrottledException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('administration')]
#[CoversClass(NotificationThrottledException::class)]
class NotificationThrottledExceptionTest extends TestCase
{
    public function testNotificationThrottledException(): void
    {
        $exception = new NotificationThrottledException(20);

        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__NOTIFICATION_THROTTLED', $exception->getErrorCode());
        static::assertSame('Notification throttled for 20 seconds.', $exception->getMessage());
        static::assertSame(['seconds' => 20], $exception->getParameters());
        static::assertSame(20, $exception->getWaitTime());
    }
}
