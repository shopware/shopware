<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\FooMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\MessageQueue\fixtures\TestTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(TaskScheduler::class)]
class TaskSchedulerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $scheduledTaskRepo;

    private MockObject&MessageBusInterface $messageBus;

    private TaskScheduler $scheduler;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->scheduler = new TaskScheduler($this->scheduledTaskRepo, $this->messageBus, new ParameterBag());

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testScheduleTasks(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => (new \DateTime())->modify('-1 second'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::once())
            ->method('dispatch')
            ->with(static::callback(function (TestTask $task) use ($taskId) {
                static::assertEquals($taskId, $task->getTaskId());

                return true;
            }))
            ->willReturn(new Envelope(new TestTask()));

        $this->scheduler->queueScheduledTasks();

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals(ScheduledTaskDefinition::STATUS_QUEUED, $task->getStatus());
    }

    public function testScheduleTasksDoesntScheduleFutureTask(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
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

    #[DataProvider('nonScheduledStatus')]
    public function testScheduleTasksDoesntScheduleNotScheduledTask(string $status): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
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

    /**
     * @return list<array{0: string}>
     */
    public static function nonScheduledStatus(): array
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
            FooMessage::class
        ));
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $context = Context::createDefaultContext();

        // tasks will be scheduled in sort sequence of their ids
        $taskId1 = '2206c460e1054f2290d86fb4379cc021';
        $taskId2 = 'cc65a904c6d246479e6c4958f262a17f';

        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId1,
                'name' => 'test_1',
                'scheduledTaskClass' => FooMessage::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => (new \DateTime())->modify('-1 second'),
            ],
        ], $context);

        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId2,
                'name' => 'test_2',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => (new \DateTime())->modify('-1 minute'),
            ],
        ], $context);

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        try {
            $this->scheduler->queueScheduledTasks();
        } catch (\Exception $exception) {
            /** @var ScheduledTaskEntity $task2Entity */
            $task2Entity = $this->scheduledTaskRepo->search(new Criteria([$taskId2]), $context)->get($taskId2);
            static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $task2Entity->getStatus());

            throw $exception;
        }
    }

    public function testGetNextExecutionTime(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $nextExecutionTime = new \DateTime();
        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => $nextExecutionTime,
            ],
            [
                'name' => 'test',
                'scheduledTaskClass' => FooMessage::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
                'nextExecutionTime' => $nextExecutionTime->modify('-10 seconds'),
            ],
        ], Context::createDefaultContext());

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        $result = $this->scheduler->getNextExecutionTime();
        static::assertInstanceOf(\DateTime::class, $result);
        // when saving the Date to the DB the microseconds aren't saved, so we can't simply compare the datetime objects
        static::assertEquals(
            $nextExecutionTime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $result->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
    }

    public function testGetNextExecutionTimeIgnoresNotScheduledTasks(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $nextExecutionTime = new \DateTime();
        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
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
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $this->scheduledTaskRepo->create([
            [
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 200,
                'defaultRunInterval' => 200,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'nextExecutionTime' => new \DateTime(),
            ],
            [
                'name' => 'test',
                'scheduledTaskClass' => FooMessage::class,
                'runInterval' => 5,
                'defaultRunInterval' => 5,
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
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $this->messageBus->expects(static::never())
            ->method('dispatch');

        static::assertNull($this->scheduler->getMinRunInterval());
    }
}
