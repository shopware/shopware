<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
final class ScheduledTaskExecutor
{
    /**
     * @internal
     *
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        private readonly EntityRepository $scheduledTaskRepository,
        private readonly LoggerInterface $exceptionLogger,
        private readonly ClockInterface $clock,
    ) {
    }

    public function execute(ScheduledTaskHandler $handler, ScheduledTask $task): void
    {
        $taskId = $task->getTaskId();

        if ($taskId === null) {
            // run task independent of the schedule
            $handler->run();

            return;
        }

        $taskEntity = $this->scheduledTaskRepository
            ->search(new Criteria([$taskId]), Context::createCLIContext())
            ->get($taskId);

        if ($taskEntity === null || !$taskEntity->isExecutionAllowed()) {
            return;
        }

        $this->markTaskRunning($task);

        try {
            $handler->run();
        } catch (\Throwable $e) {
            if ($task->shouldRescheduleOnFailure()) {
                $this->exceptionLogger->error(
                    'Scheduled task failed with: ' . $e->getMessage(),
                    [
                        'error' => $e,
                        'scheduledTask' => $task->getTaskName(),
                    ]
                );

                $this->reschedule($handler, $task, $taskEntity);

                return;
            }

            $this->markTaskFailed($task);

            throw $e;
        }

        $this->reschedule($handler, $task, $taskEntity);
    }

    private function markTaskRunning(ScheduledTask $task): void
    {
        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_RUNNING,
            ],
        ], Context::createCLIContext());
    }

    private function markTaskFailed(ScheduledTask $task): void
    {
        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
            ],
        ], Context::createCLIContext());
    }

    private function reschedule(ScheduledTaskHandler $handler, ScheduledTask $task, ScheduledTaskEntity $taskEntity): void
    {
        if ($handler instanceof DynamicallyScheduledTaskHandler) {
            $this->persistNextExecutionTime($task, $taskEntity, $handler->getNextExecutionTime($task, $taskEntity));

            return;
        }

        if (!Feature::isActive('v6.8.0.0')) {
            // BC: a subclass may still override the deprecated rescheduleTask() hook, which persists itself
            $handler->rescheduleNext($task, $taskEntity);

            return;
        }

        $this->persistNextExecutionTime($task, $taskEntity, null);
    }

    private function persistNextExecutionTime(ScheduledTask $task, ScheduledTaskEntity $taskEntity, ?\DateTimeInterface $nextExecutionTime): void
    {
        $now = $this->clock->now();

        if ($nextExecutionTime === null) {
            $nextExecutionTimeString = $taskEntity->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $nextExecutionTime = (new \DateTimeImmutable($nextExecutionTimeString))->modify(\sprintf('+%d seconds', $taskEntity->getRunInterval()));
        }

        if ($nextExecutionTime < $now) {
            $nextExecutionTime = $now;
        }

        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                'lastExecutionTime' => $now,
                'nextExecutionTime' => $nextExecutionTime,
            ],
        ], Context::createCLIContext());
    }
}
