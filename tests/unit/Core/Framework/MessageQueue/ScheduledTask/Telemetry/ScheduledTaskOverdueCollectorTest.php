<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskOverdueCollector;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Telemetry\ScheduledTaskOverdueGateway;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[CoversClass(ScheduledTaskOverdueCollector::class)]
class ScheduledTaskOverdueCollectorTest extends TestCase
{
    public function testCollectsOverdueCountFromGatewayAtTheCurrentTime(): void
    {
        $clock = new MockClock('2026-07-06 12:00:00');

        $gateway = $this->createMock(ScheduledTaskOverdueGateway::class);
        $gateway->expects($this->once())
            ->method('countOverdue')
            ->with($clock->now())
            ->willReturn(7);

        $collector = new ScheduledTaskOverdueCollector($gateway, $clock);

        $metrics = iterator_to_array($collector->collect(), preserve_keys: false);

        static::assertCount(1, $metrics);
        static::assertSame('scheduled_task.overdue.count', $metrics[0]->name);
        static::assertSame(7, $metrics[0]->value);
        static::assertSame([], $metrics[0]->labels);
    }
}
