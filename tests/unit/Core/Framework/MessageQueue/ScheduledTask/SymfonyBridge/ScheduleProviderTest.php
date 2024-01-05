<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\SymfonyBridge;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\SymfonyBridge\ScheduleProvider;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Scheduler\Generator\MessageGenerator;

/**
 * @internal
 */
#[CoversClass(\Shopware\Core\Framework\MessageQueue\ScheduledTask\SymfonyBridge\ScheduleProvider::class)]
class ScheduleProviderTest extends TestCase
{
    public function testScheduleIsBuildFromScheduledTasks(): void
    {
        $tasks = [
            new class() extends ScheduledTask {
                public static function getTaskName(): string
                {
                    return 'test_task_1';
                }

                public static function getDefaultInterval(): int
                {
                    return 10;
                }
            },
            new class() extends ScheduledTask {
                public static function getTaskName(): string
                {
                    return 'test_task_2';
                }

                public static function getDefaultInterval(): int
                {
                    return 20;
                }
            },
        ];

        $scheduleProvider = new ScheduleProvider($tasks, $this->createMock(Connection::class));

        $mockClock = new MockClock();
        $generator = new MessageGenerator($scheduleProvider, 'foo', $mockClock);

        $initialmessages = iterator_to_array($generator->getMessages(), false);

        $mockClock->sleep(20);
        $messages = iterator_to_array($generator->getMessages(), false);

        static::assertEmpty($initialmessages);
        static::assertCount(3, $messages);

        static::assertInstanceOf(ScheduledTask::class, $messages[0]);
        static::assertSame('test_task_1', $messages[0]->getTaskName());

        static::assertInstanceOf(ScheduledTask::class, $messages[1]);
        static::assertSame('test_task_1', $messages[1]->getTaskName());

        static::assertInstanceOf(ScheduledTask::class, $messages[2]);
        static::assertSame('test_task_2', $messages[2]->getTaskName());
    }

    public function testScheduleIsOverwrittenByDatabase(): void
    {
        $tasks = [
            new class() extends ScheduledTask {
                public static function getTaskName(): string
                {
                    return 'test_task_1';
                }

                public static function getDefaultInterval(): int
                {
                    return 10;
                }
            },
            new class() extends ScheduledTask {
                public static function getTaskName(): string
                {
                    return 'test_task_2';
                }

                public static function getDefaultInterval(): int
                {
                    return 20;
                }
            },
            new class() extends ScheduledTask {
                public static function getTaskName(): string
                {
                    return 'test_task_3';
                }

                public static function getDefaultInterval(): int
                {
                    return 1;
                }
            },
        ];

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociativeIndexed')->willReturn(
            [
                'test_task_1' => [
                    'run_interval' => 20,
                    'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                ],
                'test_task_3' => [
                    'run_interval' => 20,
                    'status' => ScheduledTaskDefinition::STATUS_INACTIVE,
                ],
            ]
        );

        $scheduleProvider = new ScheduleProvider($tasks, $connection);

        $mockClock = new MockClock();
        $generator = new MessageGenerator($scheduleProvider, 'foo', $mockClock);

        $initialmessages = iterator_to_array($generator->getMessages(), false);

        $mockClock->sleep(20);
        $messages = iterator_to_array($generator->getMessages(), false);

        static::assertEmpty($initialmessages);
        static::assertCount(2, $messages);

        static::assertInstanceOf(ScheduledTask::class, $messages[0]);
        static::assertSame('test_task_1', $messages[0]->getTaskName());

        static::assertInstanceOf(ScheduledTask::class, $messages[1]);
        static::assertSame('test_task_2', $messages[1]->getTaskName());
    }
}
