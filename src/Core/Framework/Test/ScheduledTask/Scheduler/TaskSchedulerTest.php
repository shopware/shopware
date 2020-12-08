<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ScheduledTask\Scheduler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\Message\RetryMessage;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TaskSchedulerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $scheduledTaskRepo;

    /**
     * @var MessageBusInterface
     */
    private $messageBus;

    /**
     * @var TaskScheduler
     */
    private $scheduler;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp(): void
    {
        $this->scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->scheduler = new TaskScheduler($this->scheduledTaskRepo, $this->messageBus);

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testScheduleTasks(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => (new \DateTime())->modify('-1 second'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (RequeueDeadMessagesTask $task) use ($taskId) {
                static::assertEquals($taskId, $task->getTaskId());

                return true;
            }))
            ->willReturn(new Envelope(new RequeueDeadMessagesTask()));

        $this->scheduler->queueScheduledTasks();

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals(ScheduledTaskDefinition::STATUS_QUEUED, $task->getStatus());
    }

    public function testScheduleTasksDoesntScheduleFutureTask(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => (new \DateTime())->modify('+1 minute'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        $this->scheduler->queueScheduledTasks();

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
    }

    /**
     * @dataProvider nonScheduledStatus
     */
    public function testScheduleTasksDoesntScheduleNotScheduledTask(string $status): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => $status,
                'nextExecutionTime' => (new \DateTime())->modify('-1 second'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        $this->scheduler->queueScheduledTasks();

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals($status, $task->getStatus());
    }

    public function nonScheduledStatus(): array
    {
        return [
            [ScheduledTaskDefinition::STATUS_RUNNING],
            [ScheduledTaskDefinition::STATUS_FAILED],
            [ScheduledTaskDefinition::STATUS_QUEUED],
            [ScheduledTaskDefinition::STATUS_INACTIVE],
        ];
    }

    public function testScheduleTasksThrowsExceptionWhenTryingToScheduleWrongClass(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'Tried to schedule "%s", but class does not extend ScheduledTask',
            TestMessage::class
        ));
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestMessage::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => (new \DateTime())->modify('-1 second'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        $this->scheduler->queueScheduledTasks();
    }

    public function testGetNextExecutionTime(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $nextExecutionTime = new \DateTime();
        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => $nextExecutionTime,
            ],
            [
                'name' => 'test',
                'scheduledTaskClass' => TestMessage::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => $nextExecutionTime->modify('+1 second'),
            ],
            [
                'name' => 'test',
                'scheduledTaskClass' => RetryMessage::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
                'nextExecutionTime' => $nextExecutionTime->modify('-10 seconds'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        $result = $this->scheduler->getNextExecutionTime();
        static::assertInstanceOf(\DateTime::class, $result);
        // when saving the Date to the DB the microseconds aren't saved, so we can't simply compare the datetime objects
        static::assertEquals($nextExecutionTime->format(\DATE_ATOM), $result->format(\DATE_ATOM));
    }

    public function testGetNextExecutionTimeIgnoresNotScheduledTasks(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $nextExecutionTime = new \DateTime();
        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => RetryMessage::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
                'nextExecutionTime' => $nextExecutionTime->modify('-10 seconds'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        static::assertNull($this->scheduler->getNextExecutionTime());
    }

    public function testGetMinRunInterval(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => new \DateTime(),
            ],
            [
                'name' => 'test',
                'scheduledTaskClass' => TestMessage::class,
                'runInterval' => 200,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => new \DateTime(),
            ],
            [
                'name' => 'test',
                'scheduledTaskClass' => RetryMessage::class,
                'runInterval' => 5,
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
                'nextExecutionTime' => new \DateTime(),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        static::assertEquals(5, $this->scheduler->getMinRunInterval());
    }

    public function testGetMinRunIntervalWhenEmpty(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        static::assertNull($this->scheduler->getMinRunInterval());
    }
}
