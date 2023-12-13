<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Shopware\Core\Framework\MessageQueue\Command\ScheduledTaskRunner;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(ScheduledTaskRunner::class)]
class ScheduledTaskRunnerTest extends TestCase
{
    public function testScheduleDirectly(): void
    {
        $scheduler = $this->createMock(TaskScheduler::class);
        $scheduler
            ->expects(static::once())
            ->method('queueScheduledTasks');

        $runner = new ScheduledTaskRunner(
            $scheduler,
            $this->createMock(CacheItemPoolInterface::class)
        );

        $tester = new CommandTester($runner);

        $tester->execute([
            '--no-wait' => true,
        ]);
    }
}
