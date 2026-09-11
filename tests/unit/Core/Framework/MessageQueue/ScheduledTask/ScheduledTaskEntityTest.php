<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScheduledTaskEntity::class)]
class ScheduledTaskEntityTest extends TestCase
{
    public function testScalarAccessorsRoundTrip(): void
    {
        $task = new ScheduledTaskEntity();

        $task->setName('name');
        $task->setScheduledTaskClass(InvalidateCacheTask::class);
        $task->setRunInterval(7);
        $task->setDefaultRunInterval(7);
        $task->setStatus('status');

        static::assertSame('name', $task->getName());
        static::assertSame(InvalidateCacheTask::class, $task->getScheduledTaskClass());
        static::assertSame(7, $task->getRunInterval());
        static::assertSame(7, $task->getDefaultRunInterval());
        static::assertSame('status', $task->getStatus());
    }

    public function testAssociationAccessorsRoundTrip(): void
    {
        $task = new ScheduledTaskEntity();

        $lastExecutionTime = new \DateTimeImmutable('2026-01-01 00:00:00');
        $nextExecutionTime = new \DateTimeImmutable('2026-01-01 00:00:00');

        $task->setLastExecutionTime($lastExecutionTime);
        $task->setNextExecutionTime($nextExecutionTime);

        static::assertSame($lastExecutionTime, $task->getLastExecutionTime());
        static::assertSame($nextExecutionTime, $task->getNextExecutionTime());
    }
}
