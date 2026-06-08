<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(ScheduledTaskHandler::class)]
class ScheduledTaskHandlerTest extends TestCase
{
    public function testInvokeDelegatesToExecutorWhenSet(): void
    {
        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([]);

        $handler = new HandlerStub($repository, $this->createMock(LoggerInterface::class));
        $handler->setScheduledTaskExecutor(new ScheduledTaskExecutor($repository, $this->createMock(LoggerInterface::class)));

        // a task without id is run directly by the executor, without touching the repository
        $task = new HandlerStubTask();

        // even with the major flag active, the executor path must not trigger the deprecation
        Feature::fake(['v6.8.0.0'], function () use ($handler, $task): void {
            $handler($task);
        });

        static::assertTrue($handler->wasCalled);
    }

    public function testInvokeThrowsDeprecationWhenNoExecutorIsSetAndMajorIsActive(): void
    {
        $handler = new HandlerStub(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
        );

        Feature::fake(['v6.8.0.0'], function () use ($handler): void {
            $this->expectException(FeatureException::class);

            $handler(new HandlerStubTask());
        });
    }

    public function testInvokeFallsBackToInlineLogicWhenNoExecutorIsSet(): void
    {
        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection()]);

        $handler = new HandlerStub($repository, $this->createMock(LoggerInterface::class));

        $task = new HandlerStubTask();
        $task->setTaskId('task-id');

        Feature::fake([], function () use ($handler, $task): void {
            $handler($task);
        });

        // task entity is not found, so the handler returns before running
        static::assertFalse($handler->wasCalled);
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
