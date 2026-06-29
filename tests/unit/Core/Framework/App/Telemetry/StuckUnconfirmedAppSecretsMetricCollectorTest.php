<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Telemetry\StuckUnconfirmedAppSecretsMetricCollector;
use Shopware\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(StuckUnconfirmedAppSecretsMetricCollector::class)]
class StuckUnconfirmedAppSecretsMetricCollectorTest extends TestCase
{
    public function testCollectYieldsThePendingSecretCount(): void
    {
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('countAppsWithUnconfirmedSecrets')
            ->with(static::isInstanceOf(Context::class))
            ->willReturn(3);

        $collector = new StuckUnconfirmedAppSecretsMetricCollector($rotationService);

        $metrics = [];
        foreach ($collector->collect() as $metric) {
            $metrics[] = $metric;
        }

        static::assertCount(1, $metrics);
        static::assertSame('app.unconfirmed_app_secrets.count', $metrics[0]->name);
        static::assertSame(3, $metrics[0]->value);
    }
}
