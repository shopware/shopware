<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\HealthConfig;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HealthConfig::class)]
class HealthConfigTest extends TestCase
{
    public function testStoresValidatedValues(): void
    {
        $config = new HealthConfig([300, 600, 1200, 2400, 3600, 14400], 5, 3, 7);

        static::assertSame([300, 600, 1200, 2400, 3600, 14400], $config->cooldownScheduleSeconds);
        static::assertSame(5, $config->degradedThreshold);
        static::assertSame(3, $config->nonTransientThreshold);
        static::assertSame(7, $config->maxSuspendedDays);
    }
}
