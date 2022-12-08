<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @package core
 */
abstract class ScheduledTaskHandler implements MessageSubscriberInterface
{
    protected EntityRepository $scheduledTaskRepository;

    public function __construct(EntityRepository $scheduledTaskRepository)
    {
        $this->scheduledTaskRepository = $scheduledTaskRepository;
    }

    public function __invoke(ScheduledTask $task): void
    {
        $taskId = $task->getTaskId();

        if ($taskId === null) {
            // run task independent of the schedule
            $this->run();

            return;
        }

        /** @var ScheduledTaskEntity|null $taskEntity */
        $taskEntity = $this->scheduledTaskRepository
            ->search(new Criteria([$taskId]), Context::createDefaultContext())
            ->get($taskId);

        if ($taskEntity === null || !$taskEntity->isExecutionAllowed()) {
            return;
        }

        $this->markTaskRunning($task);

        try {
            $this->run();
        } catch (\Throwable $e) {
            $this->markTaskFailed($task);

            throw $e;
        }

        $this->rescheduleTask($task, $taskEntity);
    }

    abstract public function run(): void;

    protected function markTaskRunning(ScheduledTask $task): void
    {
        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_RUNNING,
            ],
        ], Context::createDefaultContext());
    }

    protected function markTaskFailed(ScheduledTask $task): void
    {
        $this->scheduledTaskRepository->update([
            [
                'id' => $task->getTaskId(),
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
            ],
        ], Context::createDefaultContext());
    }

    protected function rescheduleTask(ScheduledTask $task, ScheduledTaskEntity $taskEntity): void
    {
        $now = new \DateTimeImmutable();

        $nextExecutionTimeString = $taskEntity->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $nextExecutionTime = new \DateTimeImmutable($nextExecutionTimeString);
        $newNextExecutionTime = $nextExecutionTime->modify(sprintf('+%d seconds', $taskEntity->getRunInterval()));

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
        ], Context::createDefaultContext());
    }
}
