<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageSubscriberInterface;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - Won't implement MessageSubscriberInterface anymore, tag all ScheduledTaskHandlers with #[AsMessageHandler] instead
 */
#[Package('core')]
abstract class ScheduledTaskHandler implements MessageSubscriberInterface
{
    /**
     * @deprecated tag:v6.7.0 - exceptionLogger will be required
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly ?LoggerInterface $exceptionLogger = null
    ) {
        if ($exceptionLogger === null) {
            Feature::triggerDeprecationOrThrow('v6.7.0.0', 'Constructor argument exceptionLogger is required.');
        }
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
            if ($task->shouldRescheduleOnFailure()) {
                $this->exceptionLogger?->error(
                    'Scheduled task failed with: ' . $e->getMessage(),
                    [
                        'error' => $e,
                        'scheduledTask' => $task->getTaskName(),
                    ]
                );

                $this->rescheduleTask($task, $taskEntity);

                return;
            }

            $this->markTaskFailed($task);

            throw $e;
        }

        $this->rescheduleTask($task, $taskEntity);
    }

    /**
     * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - method will be removed, tag all ScheduledTaskHandlers with #[AsMessageHandler] instead
     *
     * @return iterable<string>
     */
    public static function getHandledMessages(): iterable
    {
        $reflection = new \ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(AsMessageHandler::class);

        $messageClasses = [];
        foreach ($attributes as $attribute) {
            /** @var AsMessageHandler $messageAttribute */
            $messageAttribute = $attribute->newInstance();
            $messageClasses[] = $messageAttribute->handles;
        }

        return array_filter($messageClasses);
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
