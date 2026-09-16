<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScheduledTaskHandler::class)]
class ScheduledTaskHandlerTest extends TestCase
{
    public function testInvokeDelegatesToExecutorWhenSet(): void
    {
        $repository = new StaticEntityRepository([]);

        $handler = new HandlerStub($repository, static::createStub(LoggerInterface::class));
        $handler->setScheduledTaskExecutor(new ScheduledTaskExecutor($repository, static::createStub(LoggerInterface::class), new MockClock()));

        // a task without id is run directly by the executor, without touching the repository
        $task = new HandlerStubTask();

        // even with the major flag active, the executor path must not trigger the deprecation
        Feature::fake(['v6.8.0.0'], function () use ($handler, $task): void {
            $handler($task);
        });

        static::assertTrue($handler->wasCalled);
    }

    public function testInvokeThrowsWhenNoExecutorIsSetAndMajorIsActive(): void
    {
        $handler = new HandlerStub(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
        );

        Feature::fake(['v6.8.0.0'], function () use ($handler): void {
            try {
                $handler(new HandlerStubTask());
                static::fail('Expected MessageQueueException to be thrown');
            } catch (MessageQueueException $e) {
                static::assertSame(MessageQueueException::SCHEDULED_TASK_EXECUTOR_NOT_SET, $e->getErrorCode());
            }
        });

        static::assertFalse($handler->wasCalled);
    }

    public function testInvokeFallsBackToInlineLogicWhenNoExecutorIsSet(): void
    {
        $repository = new StaticEntityRepository([new ScheduledTaskCollection()]);

        $handler = new HandlerStub($repository, static::createStub(LoggerInterface::class));

        $task = new HandlerStubTask();
        $task->setTaskId('task-id');

        Feature::fake([], function () use ($handler, $task): void {
            $handler($task);
        });

        // task entity is not found, so the handler returns before running
        static::assertFalse($handler->wasCalled);
    }

    public function testRescheduleNextThrowsDeprecationWhenV68IsActive(): void
    {
        $handler = new HandlerStub(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
        );

        $this->expectExceptionObject(FeatureException::error(
            Feature::deprecatedMethodMessage(ScheduledTaskHandler::class, 'rescheduleNext', 'v6.8.0.0')
        ));

        Feature::fake(['v6.8.0.0'], function () use ($handler): void {
            $handler->rescheduleNext(
                static::createStub(ScheduledTask::class),
                static::createStub(ScheduledTaskEntity::class),
            );
        });
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInlineLogicRunsAndReschedulesAnAllowedTask(): void
    {
        $taskId = 'task-id';

        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId($taskId);
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);
        $taskEntity->setNextExecutionTime(new \DateTimeImmutable('2024-01-01 00:00:00'));
        $taskEntity->setRunInterval(300);

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$taskEntity])]);

        $handler = new HandlerStub($repository, static::createStub(LoggerInterface::class));

        $task = new HandlerStubTask();
        $task->setTaskId($taskId);

        $handler($task);

        static::assertTrue($handler->wasCalled);

        static::assertCount(2, $repository->updates);
        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $repository->updates[0][0]['status'] ?? null);
        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $repository->updates[1][0]['status'] ?? null);
    }
}

/**
 * @internal
 */
#[Package('framework')]
class HandlerStubTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'test.handler-stub';
    }

    public static function getDefaultInterval(): int
    {
        return 300;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class HandlerStub extends ScheduledTaskHandler
{
    public bool $wasCalled = false;

    public function run(): void
    {
        $this->wasCalled = true;
    }
}
