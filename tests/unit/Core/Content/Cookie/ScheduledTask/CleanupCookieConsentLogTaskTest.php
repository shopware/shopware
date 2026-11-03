<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTask;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupCookieConsentLogTask::class)]
class CleanupCookieConsentLogTaskTest extends TestCase
{
    public function testTaskName(): void
    {
        static::assertSame('cookie_consent_log.cleanup', CleanupCookieConsentLogTask::getTaskName());
    }

    public function testDefaultInterval(): void
    {
        static::assertSame(86400, CleanupCookieConsentLogTask::getDefaultInterval());
    }

    public function testShouldRescheduleOnFailure(): void
    {
        static::assertTrue(CleanupCookieConsentLogTask::shouldRescheduleOnFailure());
    }
}
