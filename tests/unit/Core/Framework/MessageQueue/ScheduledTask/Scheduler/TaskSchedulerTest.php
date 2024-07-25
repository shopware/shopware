<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(TaskScheduler::class)]
class TaskSchedulerTest extends TestCase
{
    /**
     * @param AggregationResult[] $aggregationResult
     */
    #[DataProvider('providerGetNextExecutionTime')]
    public function testGetNextExecutionTime(array $aggregationResult, ?\DateTime $time): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $scheduledTaskRepository->method('aggregate')->willReturn(new AggregationResultCollection($aggregationResult));

        $scheduler = new TaskScheduler(
            $scheduledTaskRepository,
            $this->createMock(MessageBusInterface::class),
            new ParameterBag()
        );

        static::assertEquals(
            $time,
            $scheduler->getNextExecutionTime()
        );
    }

    /**
     * @return iterable<array<AggregationResult[]|\DateTime|null>>
     */
    public static function providerGetNextExecutionTime(): iterable
    {
        yield [
            [],
            null,
        ];

        yield [
            [new TermsResult('nextExecutionTime', [])],
            null,
        ];

        yield [
            [new MinResult('nextExecutionTime', null)],
            null,
        ];

        yield [
            [new MinResult('nextExecutionTime', '2021-01-01T00:00:00+00:00')],
            new \DateTime('2021-01-01T00:00:00+00:00'),
        ];
    }

    /**
     * @param AggregationResult[] $aggregationResult
     */
    #[DataProvider('providerGetMinRunInterval')]
    public function testGetMinRunInterval(array $aggregationResult, ?int $time): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $scheduledTaskRepository->method('aggregate')->willReturn(new AggregationResultCollection($aggregationResult));

        $scheduler = new TaskScheduler(
            $scheduledTaskRepository,
            $this->createMock(MessageBusInterface::class),
            new ParameterBag()
        );

        static::assertEquals(
            $time,
            $scheduler->getMinRunInterval()
        );
    }

    /**
     * @return iterable<array<AggregationResult[]|int|null>>
     */
    public static function providerGetMinRunInterval(): iterable
    {
        yield [
            [],
            null,
        ];

        yield [
            [new TermsResult('runInterval', [])],
            null,
        ];

        yield [
            [new MinResult('runInterval', null)],
            null,
        ];

        yield [
            [new MinResult('runInterval', 100)],
            100,
        ];
    }

    public function testScheduleNothingMatches(): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $scheduledTaskRepository->expects(static::never())->method('update');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::never())->method('dispatch');
        $scheduler = new TaskScheduler(
            $scheduledTaskRepository,
            $bus,
            new ParameterBag()
        );

        $scheduler->queueScheduledTasks();
    }

    public function testScheduleShouldNotRunTask(): void
    {
        $scheduledTaskRepository = $this->createMock(EntityRepository::class);

        $scheduledTask = new ScheduledTaskEntity();

        $nextExecutionTime = new \DateTimeImmutable();
        $nextExecutionTime = $nextExecutionTime->modify(\sprintf('-%d seconds', InvalidateCacheTask::getDefaultInterval() + 100));

        $scheduledTask->setId('1');
        $scheduledTask->setRunInterval(InvalidateCacheTask::getDefaultInterval());
        $scheduledTask->setNextExecutionTime($nextExecutionTime);
        $scheduledTask->setScheduledTaskClass(InvalidateCacheTask::class);
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$scheduledTask]));
        $scheduledTaskRepository->expects(static::once())->method('search')->willReturn($result);
        $scheduledTaskRepository->expects(static::once())->method('update')->willReturnCallback(function (array $data, Context $context) {
            static::assertCount(1, $data);
            $data = $data[0];
            static::assertArrayHasKey('id', $data);
            static::assertArrayHasKey('nextExecutionTime', $data);
            static::assertArrayHasKey('status', $data);
            static::assertEquals('1', $data['id']);
            static::assertEquals(ScheduledTaskDefinition::STATUS_SKIPPED, $data['status']);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::never())->method('dispatch');
        $scheduler = new TaskScheduler(
            $scheduledTaskRepository,
            $bus,
            new ParameterBag([
                'shopware.cache.invalidation.delay' => 0,
            ])
        );

        $scheduler->queueScheduledTasks();
    }

    #[DataProvider('providerScheduledTaskQueues')]
    public function testScheduledTaskQueues(int $delay, bool $expected): void
    {
        $scheduledTask = new ScheduledTaskEntity();
        $scheduledTask->setId('1');
        $scheduledTask->setRunInterval(InvalidateCacheTask::getDefaultInterval());
        $scheduledTask->setNextExecutionTime(new \DateTimeImmutable());
        $scheduledTask->setScheduledTaskClass(InvalidateCacheTask::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$scheduledTask]));

        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $scheduledTaskRepository
            ->method('search')
            ->willReturn($result);

        $scheduledTaskRepository
            ->expects(static::once())
            ->method('update')
            ->willReturnCallback(function (array $data, Context $context) use ($expected) {
                static::assertCount(1, $data);
                $data = $data[0];
                static::assertArrayHasKey('status', $data);
                static::assertArrayHasKey('id', $data);
                $status = $data['status'];
                static::assertEquals($expected ? ScheduledTaskDefinition::STATUS_QUEUED : ScheduledTaskDefinition::STATUS_SKIPPED, $status);
                static::assertEquals('1', $data['id']);

                return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
            });

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($expected ? static::once() : static::never())->method('dispatch')->willReturnCallback(function ($message) {
            static::assertInstanceOf(InvalidateCacheTask::class, $message);

            return new Envelope($message);
        });

        $scheduler = new TaskScheduler(
            $scheduledTaskRepository,
            $bus,
            new ParameterBag(['shopware.cache.invalidation.delay' => $delay])
        );

        $scheduler->queueScheduledTasks();
    }

    /**
     * @return iterable<array<int|bool>>
     */
    public static function providerScheduledTaskQueues(): iterable
    {
        yield [1, true];
        yield [0, false];
    }

    public function testScheduleWithInvalidClass(): void
    {
        $scheduledTask = new ScheduledTaskEntity();
        $scheduledTask->setId('1');
        $scheduledTask->setScheduledTaskClass('foo');

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$scheduledTask]));

        $scheduledTaskRepository = $this->createMock(EntityRepository::class);
        $scheduledTaskRepository
            ->method('search')
            ->willReturn($result);

        $scheduler = new TaskScheduler(
            $scheduledTaskRepository,
            $this->createMock(MessageBusInterface::class),
            new ParameterBag()
        );

        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Tried to schedule "foo", but class does not extend ScheduledTask');
        $scheduler->queueScheduledTasks();
    }
}
