<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskHealthCollector;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskHealthGateway;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScheduledTaskHealthCollector::class)]
class ScheduledTaskHealthCollectorTest extends TestCase
{
    public function testCollectsBacklogLatenessAndFailedCountFromGateway(): void
    {
        $clock = new MockClock('2026-07-06 12:00:00');

        $gateway = $this->createMock(ScheduledTaskHealthGateway::class);
        $gateway->expects($this->once())
            ->method('getMaxLatenessSeconds')
            ->with($clock->now())
            ->willReturn(3600);
        $gateway->expects($this->once())
            ->method('countFailed')
            ->willReturn(2);

        $collector = new ScheduledTaskHealthCollector($gateway, $clock);

        $metrics = iterator_to_array($collector->collect(), preserve_keys: false);

        static::assertCount(2, $metrics);

        static::assertSame('scheduled_task.backlog.max_lateness_seconds', $metrics[0]->name);
        static::assertSame(3600, $metrics[0]->value);
        static::assertSame([], $metrics[0]->labels);

        static::assertSame('scheduled_task.failed.count', $metrics[1]->name);
        static::assertSame(2, $metrics[1]->value);
        static::assertSame([], $metrics[1]->labels);
    }
}
