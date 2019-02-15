<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ScheduledTask\Registry;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskInterface;

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

    public function registerTasks()
    {
        /** @var ScheduledTaskCollection $alreadyRegisteredTasks */
        $alreadyRegisteredTasks = $this->scheduledTaskRepository
            ->search(new Criteria(), Context::createDefaultContext())
            ->getEntities();

        $insertPayload = $this->getInsertPayload($alreadyRegisteredTasks);

        if (count($insertPayload) > 0) {
            $this->scheduledTaskRepository->create($insertPayload, Context::createDefaultContext());
        }

        $deletionPayload = $this->getDeletionPayload($alreadyRegisteredTasks);

        if (count($deletionPayload) > 0) {
            $this->scheduledTaskRepository->delete($deletionPayload, Context::createDefaultContext());
        }
    }

    private function getInsertPayload(ScheduledTaskCollection $alreadyRegisteredTasks): array
    {
        $insertPayload = [];
        /** @var ScheduledTaskInterface $task */
        foreach ($this->tasks as $task) {
            if (!$task instanceof ScheduledTaskInterface) {
                throw new \RuntimeException(sprintf(
                    'Tried to register "%s" as scheduled task, but class does not implement ScheduledTaskInterface',
                    get_class($task)
                ));
            }

            if ($this->isAlreadyRegistered($alreadyRegisteredTasks, $task)) {
                continue;
            }

            $insertPayload[] = [
                'name' => $task::getTaskName(),
                'scheduledTaskClass' => get_class($task),
                'runInterval' => $task::getDefaultInterval(),
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
            ];
        }

        return $insertPayload;
    }

    private function getDeletionPayload(ScheduledTaskCollection $alreadyRegisteredTasks): array
    {
        $deletionPayload = [];

        /** @var ScheduledTaskEntity $registeredTask */
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
        ScheduledTaskInterface $task
    ): bool {
        return count(
            $alreadyScheduledTasks
                ->filter(function (ScheduledTaskEntity $registeredTask) use ($task) {
                    return $registeredTask->getScheduledTaskClass() === get_class($task);
                })
            ) > 0;
    }

    private function taskClassStillAvailable(ScheduledTaskEntity $registeredTask): bool
    {
        foreach ($this->tasks as $task) {
            if ($registeredTask->getScheduledTaskClass() === get_class($task)) {
                return true;
            }
        }

        return false;
    }
}
