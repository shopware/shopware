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
#[CoversClass(ScheduleProvider::class)]
class ScheduleProviderTest extends TestCase
{
    public function testScheduleIsBuildFromScheduledTasks(): void
    {
        $tasks = [
            new TestTask1(),
            new TestTask2(),
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
            new TestTask1(),
            new TestTask2(),
            new TestTask3(),
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

/**
 * @internal
 */
class TestTask1 extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test_task_1';
    }

    public static function getDefaultInterval(): int
    {
        return 10;
    }
}

/**
 * @internal
 */
class TestTask2 extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test_task_2';
    }

    public static function getDefaultInterval(): int
    {
        return 20;
    }
}

/**
 * @internal
 */
class TestTask3 extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test_task_3';
    }

    public static function getDefaultInterval(): int
    {
        return 30;
    }
}
