<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\ScheduledTask;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\DummyScheduledTaskHandler;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestTask;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ScheduledTaskHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityRepository $scheduledTaskRepo;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');
    }

    /**
     * @dataProvider allowedStatus
     */
    public function testHandle(string $status): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $interval = 300;

        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => $interval,
                'defaultRunInterval' => $interval,
                'status' => $status,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);

        static::assertTrue($handler->wasCalled());

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);

        $newOriginalNextExecution = clone $originalNextExecution;
        $newOriginalNextExecution->modify(sprintf('+%d seconds', $interval));
        $newOriginalNextExecutionString = $newOriginalNextExecution->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $nextExecutionTimeString = $task->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
        static::assertEquals($newOriginalNextExecutionString, $nextExecutionTimeString);
        static::assertNotEquals($originalNextExecution->format(\DATE_ATOM), $task->getNextExecutionTime()->format(\DATE_ATOM));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function allowedStatus(): array
    {
        return [
            [ScheduledTaskDefinition::STATUS_QUEUED],
            [ScheduledTaskDefinition::STATUS_FAILED],
        ];
    }

    public function testHandleWhenNewNextExecutionTimeLessThanNowTime(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-24 hours');
        $interval = 60;

        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => $interval,
                'defaultRunInterval' => $interval,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);
        $nowTime = new \DateTime();

        static::assertTrue($handler->wasCalled());

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);

        static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
        static::assertGreaterThan(
            $task->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $nowTime->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
        static::assertNotEquals($originalNextExecution->format(\DATE_ATOM), $task->getNextExecutionTime()->format(\DATE_ATOM));
    }

    public function testHandleOnException(): void
    {
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $originalNextExecution = (new \DateTime())->modify('-10 seconds');
        $this->scheduledTaskRepo->create([
            [
                'id' => $taskId,
                'name' => 'test',
                'scheduledTaskClass' => TestTask::class,
                'runInterval' => 300,
                'defaultRunInterval' => 300,
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => $originalNextExecution,
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId, true);

        $exception = null;

        try {
            $handler($task);
        } catch (\Exception $exception) {
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
        $this->connection->executeStatement('DELETE FROM scheduled_task');

        $taskId = Uuid::randomHex();
        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);

        static::assertFalse($handler->wasCalled());
    }

    /**
     * @dataProvider notAllowedStatus
     */
    public function testHandleIgnoresWhenTaskIsNotAllowedForExecution(string $status): void
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
                'nextExecutionTime' => new \DateTime(),
            ],
        ], Context::createDefaultContext());

        $task = new TestTask();
        $task->setTaskId($taskId);

        $handler = new DummyScheduledTaskHandler($this->scheduledTaskRepo, $taskId);
        $handler($task);

        static::assertFalse($handler->wasCalled());

        /** @var ScheduledTaskEntity $task */
        $task = $this->scheduledTaskRepo->search(new Criteria([$taskId]), Context::createDefaultContext())->get($taskId);
        static::assertEquals($status, $task->getStatus());
    }

    /**
     * @return list<array{0: string}>
     */
    public static function notAllowedStatus(): array
    {
        return [
            [ScheduledTaskDefinition::STATUS_RUNNING],
            [ScheduledTaskDefinition::STATUS_SCHEDULED],
            [ScheduledTaskDefinition::STATUS_INACTIVE],
        ];
    }
}
