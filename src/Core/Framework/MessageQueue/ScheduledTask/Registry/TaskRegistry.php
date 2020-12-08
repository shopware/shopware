<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;

class TaskRegistry
{
    /**
     * @var EntityRepositoryInterface
     */
    private $scheduledTaskRepository;

    /**
     * @var iterable
     */
    private $tasks;

    public function __construct(
        iterable $tasks,
        EntityRepositoryInterface $scheduledTaskRepository
    ) {
        $this->tasks = $tasks;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
    }

    public function registerTasks(): void
    {
        /** @var ScheduledTaskCollection $alreadyRegisteredTasks */
        $alreadyRegisteredTasks = $this->scheduledTaskRepository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities();

        $this->insertNewTasks($alreadyRegisteredTasks);

        $deletionPayload = $this->getDeletionPayload($alreadyRegisteredTasks);

        if (\count($deletionPayload) > 0) {
            $this->scheduledTaskRepository->delete($deletionPayload, Context::createDefaultContext());
        }
    }

    private function insertNewTasks(ScheduledTaskCollection $alreadyRegisteredTasks): void
    {
        /** @var ScheduledTask $task */
        foreach ($this->tasks as $task) {
            if (!$task instanceof ScheduledTask) {
                throw new \RuntimeException(sprintf(
                    'Tried to register "%s" as scheduled task, but class does not extend ScheduledTask',
                    \get_class($task)
                ));
            }

            if ($this->isAlreadyRegistered($alreadyRegisteredTasks, $task)) {
                continue;
            }

            try {
                $this->scheduledTaskRepository->create([
                    [
                        'name' => $task::getTaskName(),
                        'scheduledTaskClass' => \get_class($task),
                        'runInterval' => $task::getDefaultInterval(),
                        'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                    ],
                ], Context::createDefaultContext());
            } catch (UniqueConstraintViolationException $e) {
                // this can happen if the function runs multiple times simultaneously
                // we just care that the task is registered afterward so we can safely ignore the error
            }
        }
    }

    private function getDeletionPayload(ScheduledTaskCollection $alreadyRegisteredTasks): array
    {
        $deletionPayload = [];

        foreach ($alreadyRegisteredTasks as $registeredTask) {
            if ($this->taskClassStillAvailable($registeredTask)) {
                continue;
            }

            $deletionPayload[] = [
                'id' => $registeredTask->getId(),
            ];
        }

        return $deletionPayload;
    }

    private function isAlreadyRegistered(
        ScheduledTaskCollection $alreadyScheduledTasks,
        ScheduledTask $task
    ): bool {
        return \count(
            $alreadyScheduledTasks
                ->filter(function (ScheduledTaskEntity $registeredTask) use ($task) {
                    return $registeredTask->getScheduledTaskClass() === \get_class($task);
                })
        ) > 0;
    }

    private function taskClassStillAvailable(ScheduledTaskEntity $registeredTask): bool
    {
        foreach ($this->tasks as $task) {
            if ($registeredTask->getScheduledTaskClass() === \get_class($task)) {
                return true;
            }
        }

        return false;
    }
}
