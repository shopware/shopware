<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Registry;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTask;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry
 */
class TaskRegistryTest extends TestCase
{
    /**
     * @var EntityRepository&MockObject
     */
    private EntityRepository $scheduleTaskRepository;

    protected function setUp(): void
    {
        $this->scheduleTaskRepository = $this->createMock(EntityRepository::class);
    }

    public function testNewTasksAreCreated(): void
    {
        $tasks = [new InvalidateCacheTask(), new CreateAliasTask(), new CleanupCartTask()];
        $parameterBag = new ParameterBag([
            'shopware.cache.invalidation.delay' => 10,
            'elasticsearch.enabled' => false,
        ]);

        $registry = new TaskRegistry($tasks, $this->scheduleTaskRepository, $parameterBag);

        $registeredTask = new ScheduledTaskEntity();

        $registeredTask->setId('1');
        $registeredTask->setName(CleanupCartTask::getTaskName());
        $registeredTask->setRunInterval(CleanupCartTask::getDefaultInterval());
        $registeredTask->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $registeredTask->setNextExecutionTime(new \DateTimeImmutable());
        $registeredTask->setScheduledTaskClass(CleanupCartTask::class);
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$registeredTask]));
        $this->scheduleTaskRepository->expects(static::once())->method('search')->willReturn($result);
        $this->scheduleTaskRepository->expects(static::never())->method('update');
        $this->scheduleTaskRepository->expects(static::never())->method('delete');
        $this->scheduleTaskRepository->expects(static::exactly(2))->method('create')->withConsecutive(
            [
                [
                    [
                        'name' => InvalidateCacheTask::getTaskName(),
                        'scheduledTaskClass' => InvalidateCacheTask::class,
                        'runInterval' => InvalidateCacheTask::getDefaultInterval(),
                        'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                    ],
                ],
                Context::createDefaultContext(),
            ],
            [
                [
                    [
                        'name' => CreateAliasTask::getTaskName(),
                        'scheduledTaskClass' => CreateAliasTask::class,
                        'runInterval' => CreateAliasTask::getDefaultInterval(),
                        'status' => ScheduledTaskDefinition::STATUS_SKIPPED,
                    ],
                ],
                Context::createDefaultContext(),
            ]
        );

        $registry->registerTasks();
    }

    public function testInvalidTasksAreDeleted(): void
    {
        $parameterBag = new ParameterBag([]);

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, $parameterBag);

        $registeredTask = new ScheduledTaskEntity();

        $registeredTask->setId('deletedId');
        $registeredTask->setName(CleanupCartTask::getTaskName());
        $registeredTask->setRunInterval(CleanupCartTask::getDefaultInterval());
        $registeredTask->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $registeredTask->setNextExecutionTime(new \DateTimeImmutable());
        $registeredTask->setScheduledTaskClass('InvalidClass');
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$registeredTask]));
        $this->scheduleTaskRepository->expects(static::once())->method('search')->willReturn($result);
        $this->scheduleTaskRepository->expects(static::never())->method('update');
        $this->scheduleTaskRepository->expects(static::never())->method('create');
        $this->scheduleTaskRepository->expects(static::once())->method('delete')->with([
            [
                'id' => 'deletedId',
            ],
        ], Context::createDefaultContext());

        $registry->registerTasks();
    }

    public function testQueuedOrScheduledTasksShouldBecomeSkipped(): void
    {
        $tasks = [new InvalidateCacheTask(), new CreateAliasTask()];

        // passing these parameters so these task shouldRun return false
        $parameterBag = new ParameterBag([
            'shopware.cache.invalidation.delay' => 0,
            'elasticsearch.enabled' => false,
        ]);

        $registry = new TaskRegistry($tasks, $this->scheduleTaskRepository, $parameterBag);

        $queuedTask = new ScheduledTaskEntity();
        $scheduledTask = new ScheduledTaskEntity();

        $queuedTask->setId('queuedTask');
        $queuedTask->setName(InvalidateCacheTask::getTaskName());
        $queuedTask->setRunInterval(InvalidateCacheTask::getDefaultInterval());
        $queuedTask->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);
        $queuedTask->setNextExecutionTime(new \DateTimeImmutable());
        $queuedTask->setScheduledTaskClass(InvalidateCacheTask::class);

        $scheduledTask->setId('scheduledTask');
        $scheduledTask->setName(CreateAliasTask::getTaskName());
        $scheduledTask->setRunInterval(CreateAliasTask::getDefaultInterval());
        $scheduledTask->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $scheduledTask->setNextExecutionTime(new \DateTimeImmutable());
        $scheduledTask->setScheduledTaskClass(CreateAliasTask::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$queuedTask, $scheduledTask]));

        $this->scheduleTaskRepository->expects(static::once())->method('search')->willReturn($result);

        $this->scheduleTaskRepository->expects(static::exactly(1))->method('update')->willReturnCallback(function (array $data, Context $context) {
            static::assertCount(2, $data);

            static::assertNotEmpty($data[0]);
            static::assertNotEmpty($data[1]);

            [ $queueTaskPayload, $scheduledTaskPayload ] = $data;

            static::assertArrayHasKey('status', $queueTaskPayload);
            static::assertArrayHasKey('status', $scheduledTaskPayload);
            static::assertArrayHasKey('id', $queueTaskPayload);
            static::assertArrayHasKey('id', $scheduledTaskPayload);
            static::assertEquals(ScheduledTaskDefinition::STATUS_SKIPPED, $queueTaskPayload['status']);
            static::assertEquals('queuedTask', $queueTaskPayload['id']);
            static::assertEquals(ScheduledTaskDefinition::STATUS_SKIPPED, $scheduledTaskPayload['status']);
            static::assertEquals('scheduledTask', $scheduledTaskPayload['id']);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->scheduleTaskRepository->expects(static::never())->method('delete');
        $this->scheduleTaskRepository->expects(static::never())->method('create');

        $registry->registerTasks();
    }

    public function testQueuedOrSkippedTasksShouldBecomeScheduled(): void
    {
        $tasks = [new InvalidateCacheTask(), new CreateAliasTask()];

        // passing these parameters so these task shouldRun return true
        $parameterBag = new ParameterBag([
            'shopware.cache.invalidation.delay' => 10,
            'elasticsearch.enabled' => true,
        ]);

        $registry = new TaskRegistry($tasks, $this->scheduleTaskRepository, $parameterBag);

        $queuedTask = new ScheduledTaskEntity();
        $skippedTask = new ScheduledTaskEntity();

        $queuedTask->setId('queuedTask');
        $queuedTask->setName(InvalidateCacheTask::getTaskName());
        $queuedTask->setRunInterval(InvalidateCacheTask::getDefaultInterval());
        $queuedTask->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);
        $queuedTask->setNextExecutionTime(new \DateTimeImmutable());
        $queuedTask->setScheduledTaskClass(InvalidateCacheTask::class);

        $skippedTask->setId('skippedTask');
        $skippedTask->setName(CreateAliasTask::getTaskName());
        $skippedTask->setRunInterval(CreateAliasTask::getDefaultInterval());
        $skippedTask->setStatus(ScheduledTaskDefinition::STATUS_SKIPPED);
        $skippedTask->setNextExecutionTime(new \DateTimeImmutable());
        $skippedTask->setScheduledTaskClass(CreateAliasTask::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$queuedTask, $skippedTask]));

        $this->scheduleTaskRepository->expects(static::once())->method('search')->willReturn($result);

        $this->scheduleTaskRepository->expects(static::exactly(1))->method('update')->willReturnCallback(function (array $data, Context $context) {
            static::assertCount(2, $data);

            static::assertNotEmpty($data[0]);
            static::assertNotEmpty($data[1]);

            [ $queueTaskPayload, $skippedTaskPayload ] = $data;

            static::assertArrayHasKey('status', $queueTaskPayload);
            static::assertArrayHasKey('status', $skippedTaskPayload);
            static::assertArrayHasKey('id', $queueTaskPayload);
            static::assertArrayHasKey('id', $skippedTaskPayload);
            static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $queueTaskPayload['status']);
            static::assertEquals('queuedTask', $queueTaskPayload['id']);
            static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $skippedTaskPayload['status']);
            static::assertEquals('skippedTask', $skippedTaskPayload['id']);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->scheduleTaskRepository->expects(static::never())->method('delete');
        $this->scheduleTaskRepository->expects(static::never())->method('create');

        $registry->registerTasks();
    }
}
