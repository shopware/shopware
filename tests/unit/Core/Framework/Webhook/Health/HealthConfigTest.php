<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;
use Shopware\Core\Framework\Webhook\WebhookException;

/**
 * @internal
 */
#[CoversClass(HealthConfig::class)]
class HealthConfigTest extends TestCase
{
    public function testAcceptsProductionDefaults(): void
    {
        $config = new HealthConfig([300, 600, 1200, 2400, 3600, 14400], 5, 3, 7);

        static::assertSame([300, 600, 1200, 2400, 3600, 14400], $config->cooldownScheduleSeconds);
        static::assertSame(5, $config->degradedThreshold);
        static::assertSame(3, $config->nonTransientThreshold);
        static::assertSame(7, $config->maxSuspendedDays);
    }

    public function testThrowsWhenCooldownScheduleIsEmpty(): void
    {
        $this->expectExceptionObject(WebhookException::invalidHealthConfig('cooldown_schedule_seconds must not be empty'));

        new HealthConfig([], 5, 3, 7);
    }

    public function testThrowsWhenDegradedThresholdBelowOne(): void
    {
        $this->expectExceptionObject(WebhookException::invalidHealthConfig('degraded_threshold must be at least 1'));

        new HealthConfig([300], 0, 3, 7);
    }

    public function testThrowsWhenNonTransientThresholdBelowOne(): void
    {
        $this->expectExceptionObject(WebhookException::invalidHealthConfig('non_transient_threshold must be at least 1'));

        new HealthConfig([300], 5, 0, 7);
    }

    public function testThrowsWhenMaxSuspendedDaysBelowOne(): void
    {
        $this->expectExceptionObject(WebhookException::invalidHealthConfig('max_suspended_days must be between 1 and 14'));

        new HealthConfig([300], 5, 3, 0);
    }

    public function testThrowsWhenMaxSuspendedDaysAboveFourteen(): void
    {
        $this->expectExceptionObject(WebhookException::invalidHealthConfig('max_suspended_days must be between 1 and 14'));

        new HealthConfig([300], 5, 3, 15);
    }
}
