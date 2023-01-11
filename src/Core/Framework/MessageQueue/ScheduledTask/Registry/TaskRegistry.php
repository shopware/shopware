<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Package('core')]
class TaskRegistry
{
    private EntityRepository $scheduledTaskRepository;

    /**
     * @var iterable<ScheduledTask>
     */
    private iterable $tasks;

    private ParameterBagInterface $parameterBag;

    /**
     * @internal
     *
     * @param iterable<int, ScheduledTask> $tasks
     */
    public function __construct(
        iterable $tasks,
        EntityRepository $scheduledTaskRepository,
        ParameterBagInterface $parameterBag
    ) {
        $this->tasks = $tasks;
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->parameterBag = $parameterBag;
    }

    public function registerTasks(): void
    {
        $context = Context::createDefaultContext();

        /** @var ScheduledTaskCollection $alreadyRegisteredTasks */
        $alreadyRegisteredTasks = $this->scheduledTaskRepository
            ->search(new Criteria(), $context)
            ->getEntities();

        $this->insertNewTasks($alreadyRegisteredTasks);

        $deletionPayload = $this->getDeletionPayload($alreadyRegisteredTasks);

        if (\count($deletionPayload) > 0) {
            $this->scheduledTaskRepository->delete($deletionPayload, $context);
        }

        $deletedIds = array_column($deletionPayload, 'id');

        $alreadyRegisteredTasks = $alreadyRegisteredTasks->filter(function (ScheduledTaskEntity $scheduledTask) use ($deletedIds) {
            return !\in_array($scheduledTask->getId(), $deletedIds, true);
        });

        $this->updateTaskStatus($alreadyRegisteredTasks, $context);
    }

    private function insertNewTasks(ScheduledTaskCollection $alreadyRegisteredTasks): void
    {
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

            $validTask = $task::shouldRun($this->parameterBag);

            try {
                $this->scheduledTaskRepository->create([
                    [
                        'name' => $task::getTaskName(),
                        'scheduledTaskClass' => \get_class($task),
                        'runInterval' => $task::getDefaultInterval(),
                        'status' => $validTask ? ScheduledTaskDefinition::STATUS_SCHEDULED : ScheduledTaskDefinition::STATUS_SKIPPED,
                    ],
                ], Context::createDefaultContext());
            } catch (UniqueConstraintViolationException $e) {
                // this can happen if the function runs multiple times simultaneously
                // we just care that the task is registered afterward so we can safely ignore the error
            }
        }
    }

    /**
     * @return list<array{id: string}>
     */
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

    private function updateTaskStatus(ScheduledTaskCollection $registeredTasks, Context $context): void
    {
        $payload = [];

        /** @var ScheduledTaskEntity $registeredTask */
        foreach ($registeredTasks as $registeredTask) {
            foreach ($this->tasks as $task) {
                if ($registeredTask->getName() !== $task::getTaskName()) {
                    continue;
                }

                if (!$task::shouldRun($this->parameterBag) && \in_array($registeredTask->getStatus(), [ScheduledTaskDefinition::STATUS_QUEUED, ScheduledTaskDefinition::STATUS_SCHEDULED], true)) {
                    $payload[] = [
                        'id' => $registeredTask->getId(),
                        'nextExecutionTime' => $this->calculateNextExecutionTime($registeredTask),
                        'status' => ScheduledTaskDefinition::STATUS_SKIPPED,
                    ];

                    continue;
                }

                if ($task::shouldRun($this->parameterBag) && \in_array($registeredTask->getStatus(), [ScheduledTaskDefinition::STATUS_QUEUED, ScheduledTaskDefinition::STATUS_SKIPPED], true)) {
                    $payload[] = [
                        'id' => $registeredTask->getId(),
                        'nextExecutionTime' => $this->calculateNextExecutionTime($registeredTask),
                        'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                    ];
                }
            }
        }

        if (\count($payload) === 0) {
            return;
        }

        $this->scheduledTaskRepository->update($payload, $context);
    }

    private function calculateNextExecutionTime(ScheduledTaskEntity $taskEntity): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();

        $nextExecutionTimeString = $taskEntity->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $nextExecutionTime = new \DateTimeImmutable($nextExecutionTimeString);
        $newNextExecutionTime = $nextExecutionTime->modify(sprintf('+%d seconds', $taskEntity->getRunInterval()));

        if ($newNextExecutionTime < $now) {
            return $now;
        }

        return $newNextExecutionTime;
    }
}
