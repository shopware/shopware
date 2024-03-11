<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskRunner;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[CoversClass(TaskRunner::class)]
class TaskRunnerTest extends TestCase
{
    public function testNonExistingTask(): void
    {
        /** @var StaticEntityRepository<ScheduledTaskCollection> $scheduledTaskRepository */
        $scheduledTaskRepository = new StaticEntityRepository([new ScheduledTaskCollection()]);
        $taskRunner = new TaskRunner([], $scheduledTaskRepository);

        $this->expectException(MessageQueueException::class);
        $this->expectExceptionMessage('Could not find scheduled task with name "non-existing-task"');
        $taskRunner->runSingleTask('non-existing-task', Context::createDefaultContext());
    }

    public function testRunTaskTriggersHandler(): void
    {
        $handler = new TestTaskHandler();
        $handler2 = new TestTask2Handler();
        $invalid = $this->createMock(StaticEntityRepository::class);
        $invalid->expects(static::never())->method(static::anything());

        $taskRunner = new TaskRunner([$handler, $handler2, $invalid], $this->getRepository());
        $taskRunner->runSingleTask('task-id', Context::createDefaultContext());

        static::assertTrue($handler->called);
        static::assertFalse($handler2->called);
    }

    /**
     * @return StaticEntityRepository<ScheduledTaskCollection>
     */
    private function getRepository(): StaticEntityRepository
    {
        $task = new ScheduledTaskEntity();
        $task->setId('task-id');
        $task->setScheduledTaskClass(TestTask::class);

        // @phpstan-ignore-next-line
        return new StaticEntityRepository([new ScheduledTaskCollection([$task])]);
    }
}

/**
 * @internal
 */
class TestTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test.task';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}

/**
 * @internal
 *
 * @final
 */
#[AsMessageHandler(handles: TestTask::class)]
class TestTaskHandler extends ScheduledTaskHandler
{
    public bool $called = false;

    public function __construct()
    {
    }

    public function __invoke(ScheduledTask $task): void
    {
        $this->run();
    }

    public function run(): void
    {
        $this->called = true;
    }
}

/**
 * @internal
 *
 * @final
 */
#[AsMessageHandler()]
class TestTask2Handler extends ScheduledTaskHandler
{
    public bool $called = false;

    public function __construct()
    {
    }

    public function __invoke(ScheduledTask $task): void
    {
        $this->run();
    }

    public function run(): void
    {
        $this->called = true;
    }
}
