<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\Exception\MessageFailedException;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\ScheduledTask\fixtures\DummyScheduledTaskHandler;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

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

    public function testHandle()
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::uuid4()->getHex();
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
        static::assertEquals($task->getLastExecutionTime()->modify('+300 seconds'), $task->getLastExecutionTime());
        static::assertNotEquals($originalNextExecution->format(DATE_ATOM), $task->getNextExecutionTime()->format(DATE_ATOM));
    }

    public function testHandleOnException()
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::uuid4()->getHex();
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
            $exception = $e->getPrevious();
        }

        static::assertInstanceOf(\RuntimeException::class, $exception);
        static::assertEquals('This Exception should be thrown', $exception->getMessage());

        static::assertTrue($handler->wasCalled());

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals(ScheduledTaskDefinition::STATUS_FAILED, $task->getStatus());
    }

    public function testHandleIgnoresIfTaskIsNotFound()
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::uuid4()->getHex();
        $task = new RequeueDeadMessagesTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);

        static::assertFalse($handler->wasCalled());
    }

    /**
     * @dataProvider notQueuedStatus
     *
     * @param string $status
     */
    public function testHandleIgnoresWhenTaskIsNotQueued(string $status)
    {
        $this->connection->exec('DELETE FROM scheduled_task');

        $taskId = Uuid::uuid4()->getHex();
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

    public function notQueuedStatus()
    {
        return [
            [ScheduledTaskDefinition::STATUS_FAILED],
            [ScheduledTaskDefinition::STATUS_RUNNING],
            [ScheduledTaskDefinition::STATUS_SCHEDULED],
            [ScheduledTaskDefinition::STATUS_INACTIVE],
        ];
    }
}
