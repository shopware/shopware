<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

        $handler->runTask($task);

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

                $handler->reschedule($task, $taskEntity);

                return;
            }

            $handler->failTask($task);

            throw $e;
        }

        $handler->reschedule($task, $taskEntity);
    }
}
