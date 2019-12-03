<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Test\ScheduledTask\fixtures\DummyScheduledTaskHandler;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class ScheduledTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $scheduledTaskRepo;

    public function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');
    }

    public function testHandle(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new RequeueDeadMessagesTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);

        static::assertTrue($handler->wasCalled());

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
        static::assertEquals($task->getLastExecutionTime()->modify('+300 seconds'), $task->getNextExecutionTime());
        static::assertNotEquals($originalNextExecution->format(DATE_ATOM), $task->getNextExecutionTime()->format(DATE_ATOM));
    }

    public function testHandleOnException(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => RequeueDeadMessagesTask::class,
                'runInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new RequeueDeadMessagesTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId, true);

        $exception = null;

        try {
            $handler($task);
        } catch (MessageFailedException $e) {
            $exception = $e->getException();
        }

        static::assertInstanceOf(\RuntimeException::class, $exception);
        static::assertEquals('This Exception should be thrown', $exception->getMessage());

        static::assertTrue($handler->wasCalled());

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals(ScheduledTaskDefinition::STATUS_FAILED, $task->getStatus());
    }

    public function testHandleIgnoresIfTaskIsNotFound(): void
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $task = new RequeueDeadMessagesTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);

        static::assertFalse($handler->wasCalled());
    }

    /**
     * @dataProvider notQueuedStatus
     */
    public function testHandleIgnoresWhenTaskIsNotQueued(string $status): void
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
                'nextExecutionTime' => new \DateTime(),
            ],
        ], Context::createDefaultContext());

        $task = new RequeueDeadMessagesTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);

        static::assertFalse($handler->wasCalled());

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals($status, $task->getStatus());
    }

    public function notQueuedStatus(): array
    {
        return [
            [ScheduledTaskDefinition::STATUS_FAILED],
            [ScheduledTaskDefinition::STATUS_RUNNING],
            [ScheduledTaskDefinition::STATUS_SCHEDULED],
            [ScheduledTaskDefinition::STATUS_INACTIVE],
        ];
    }
}
