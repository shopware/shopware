<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Notification\NotificationException;

/**
 * @internal
 */
#[CoversClass(NotificationException::class)]
class NotificationExceptionTest extends TestCase
{
    public function testAdminApiSourceExpected(): void
    {
        $exception = NotificationException::invalidAdminSource(SystemSource::class);

        static::assertSame(InvalidContextSourceException::class, $exception::class);
        static::assertSame(ApiException::API_INVALID_CONTEXT_SOURCE, $exception->getErrorCode());
    }
}
