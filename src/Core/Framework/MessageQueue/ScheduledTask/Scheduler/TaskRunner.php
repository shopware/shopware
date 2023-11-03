<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageQueueException;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

/**
 * @internal
 */
#[Package('core')]
class TaskRunner
{
    /**
     * @param iterable<object> $taskHandler
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        private readonly iterable $taskHandler,
        private readonly EntityRepository $scheduledTaskRepository,
    ) {
    }

    public function runSingleTask(string $taskName, Context $context): void
    {
        $scheduledTask = $this->fetchTask($taskName, $context);

        // Set status to allow running it
        $this->scheduledTaskRepository->update([
            [
                'id' => $scheduledTask->getId(),
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
                'nextExecutionTime' => new \DateTime(),
            ],
        ], $context);

        // Create task
        /** @var class-string<ScheduledTask> $className */
        $className = $scheduledTask->getScheduledTaskClass();
        $task = new $className();
        $task->setTaskId($scheduledTask->getId());

        foreach ($this->taskHandler as $handler) {
            if (!$handler instanceof ScheduledTaskHandler) {
                continue;
            }

            $handledMessages = $handler::getHandledMessages();

            if ($handledMessages instanceof \Traversable) {
                $handledMessages = iterator_to_array($handledMessages);
            }

            if (!\in_array($className, $handledMessages, true)) {
                continue;
            }

            // calls the __invoke() method of the abstract ScheduledTaskHandler
            $handler($task);
        }
    }

    private function fetchTask(string $taskName, Context $context): ScheduledTaskEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $taskName));

        /** @var ScheduledTaskEntity|null $task */
        $task = $this->scheduledTaskRepository->search($criteria, $context)->first();

        if ($task === null) {
            throw MessageQueueException::cannotFindTaskByName($taskName);
        }

        return $task;
    }
}
