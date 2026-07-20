<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ScheduledTaskExecutorCompilerPass;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Symfony\Component\Clock\ClockAwareTrait;

#[Package('framework')]
abstract class ScheduledTaskHandler
{
    use ClockAwareTrait;

    private ?ScheduledTaskExecutor $scheduledTaskExecutor = null;

    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly LoggerInterface $exceptionLogger,
    ) {
    }

    public function __invoke(ScheduledTask $task): void
    {
        if ($this->scheduledTaskExecutor !== null) {
            $this->scheduledTaskExecutor->execute($this, $task);

            return;
        }

        if (Feature::isActive('v6.8.0.0')) {
            throw MessageQueueException::scheduledTaskExecutorNotSet(static::class);
        }

        $this->runLegacy($task);
    }

    /**
     * @internal injected by the {@see ScheduledTaskExecutorCompilerPass}
     */
    public function setScheduledTaskExecutor(ScheduledTaskExecutor $scheduledTaskExecutor): void
    {
        $this->scheduledTaskExecutor = $scheduledTaskExecutor;
    }

    abstract public function run(): void;

    /**
     * @internal invoked by the {@see ScheduledTaskExecutor} to honor a subclass that overrides the deprecated
     * {@see rescheduleTask()} hook. Implement {@see DynamicallyScheduledTaskHandler} instead of overriding this.
     *
     * @deprecated tag:v6.8.0 - reason:becomes-internal - will be removed together with the {@see rescheduleTask()}
     * hook; the executor will always persist the schedule itself and use {@see DynamicallyScheduledTaskHandler}
     * for custom timing. Still called from inside the core, so it does not trigger a deprecation itself.
     */
    public function rescheduleNext(ScheduledTask $task, ScheduledTaskEntity $taskEntity): void
    {
        // Only when a subclass actually overrides the deprecated rescheduleTask() hook do we route through it,
        // so its custom logic (and the deprecation nudge it triggers) keeps working until the hook is removed in
        // v6.8.0.0. The default handler never overrides it, so it takes the doRescheduleTask() path and no
        // deprecation is emitted.
        if ((new \ReflectionMethod($this, 'rescheduleTask'))->getDeclaringClass()->getName() !== self::class) {
            $this->rescheduleTask($task, $taskEntity);

            return;
        }

        $this->doRescheduleTask($task, $taskEntity);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, the task state transitions are handled by the {@see ScheduledTaskExecutor}
     */
    protected function markTaskRunning(ScheduledTask $task): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->doMarkTaskRunning($task);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, the task state transitions are handled by the {@see ScheduledTaskExecutor}
     */
    protected function markTaskFailed(ScheduledTask $task): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->doMarkTaskFailed($task);
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed, the task state transitions are handled by the {@see ScheduledTaskExecutor}
     */
    protected function rescheduleTask(ScheduledTask $task, ScheduledTaskEntity $taskEntity): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->doRescheduleTask($task, $taskEntity);
    }

    private function doMarkTaskRunning(ScheduledTask $task): void
    {
        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_RUNNING,
            ],
        ], Context::createCLIContext());
    }

    private function doMarkTaskFailed(ScheduledTask $task): void
    {
        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
            ],
        ], Context::createCLIContext());
    }

    private function doRescheduleTask(ScheduledTask $task, ScheduledTaskEntity $taskEntity): void
    {
        $now = $this->now();

        $nextExecutionTimeString = $taskEntity->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $newNextExecutionTime = (new \DateTimeImmutable($nextExecutionTimeString))->modify(\sprintf('+%d seconds', $taskEntity->getRunInterval()));

        if ($newNextExecutionTime < $now) {
            $newNextExecutionTime = $now;
        }

        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'lastExecutionTime' => $now,
                'nextExecutionTime' => $newNextExecutionTime,
            ],
        ], Context::createCLIContext());
    }

    private function runLegacy(ScheduledTask $task): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            \sprintf(
                'The scheduled task handler "%s" was invoked without a "%s". The inline execution logic in "%s" is deprecated and will be removed. Register the handler as a "messenger.message_handler" service so the "%s" can inject the executor.',
                static::class,
                ScheduledTaskExecutor::class,
                self::class,
                ScheduledTaskExecutorCompilerPass::class,
            )
        );

        $taskId = $task->getTaskId();

        if ($taskId === null) {
            // run task independent of the schedule
            $this->run();

            return;
        }

        $taskEntity = $this->scheduledTaskRepository
            ->search(new Criteria([$taskId]), Context::createCLIContext())
            ->get($taskId);

        if ($taskEntity === null || !$taskEntity->isExecutionAllowed()) {
            return;
        }

        $this->doMarkTaskRunning($task);

        try {
            $this->run();
        } catch (\Throwable $e) {
            if ($task->shouldRescheduleOnFailure()) {
                $this->exceptionLogger->error(
                    'Scheduled task failed with: ' . $e->getMessage(),
                    [
                        'error' => $e,
                        'scheduledTask' => $task->getTaskName(),
                    ]
                );

                $this->doRescheduleTask($task, $taskEntity);

                return;
            }

            $this->doMarkTaskFailed($task);

            throw $e;
        }

        $this->doRescheduleTask($task, $taskEntity);
    }
}
