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
    /**
     * @internal
     *
     * @param iterable<int, ScheduledTask> $tasks
     */
    public function __construct(
        private readonly iterable $tasks,
        private readonly EntityRepository $scheduledTaskRepository,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    public function registerTasks(): void
    {
        $context = Context::createDefaultContext();

        /** @var ScheduledTaskCollection $alreadyRegisteredTasks */
        $alreadyRegisteredTasks = $this->scheduledTaskRepository
            ->search(new Criteria(), $context)
            ->getEntities();

        $this->upsertTasks($alreadyRegisteredTasks);

        $deletionPayload = $this->getDeletionPayload($alreadyRegisteredTasks);

        if (\count($deletionPayload) > 0) {
            $this->scheduledTaskRepository->delete($deletionPayload, $context);
        }
    }

    private function upsertTasks(ScheduledTaskCollection $alreadyRegisteredTasks): void
    {
        $updates = [];
        foreach ($this->tasks as $task) {
            if (!$task instanceof ScheduledTask) {
                throw new \RuntimeException(sprintf(
                    'Tried to register "%s" as scheduled task, but class does not extend ScheduledTask',
                    $task::class
                ));
            }

            $registeredTask = $this->getAlreadyRegisteredTask($alreadyRegisteredTasks, $task);
            if ($registeredTask !== null) {
                $updates[] = $this->getUpdatePayload($registeredTask, $task);

                continue;
            }

            $this->insertTask($task);
        }

        $updates = array_values(array_filter($updates));
        if (\count($updates) > 0) {
            $this->scheduledTaskRepository->update($updates, Context::createDefaultContext());
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

    private function getAlreadyRegisteredTask(
        ScheduledTaskCollection $alreadyScheduledTasks,
        ScheduledTask $task
    ): ?ScheduledTaskEntity {
        return $alreadyScheduledTasks
                ->filter(fn (ScheduledTaskEntity $registeredTask) => $registeredTask->getScheduledTaskClass() === $task::class)
                ->first();
    }

    private function taskClassStillAvailable(ScheduledTaskEntity $registeredTask): bool
    {
        foreach ($this->tasks as $task) {
            if ($registeredTask->getScheduledTaskClass() === $task::class) {
                return true;
            }
        }

        return false;
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

    private function insertTask(ScheduledTask $task): void
    {
        $validTask = $task->shouldRun($this->parameterBag);

        try {
            $this->scheduledTaskRepository->create([
                [
                    'name' => $task->getTaskName(),
                    'scheduledTaskClass' => $task::class,
                    'runInterval' => $task->getDefaultInterval(),
                    'defaultRunInterval' => $task->getDefaultInterval(),
                    'status' => $validTask ? ScheduledTaskDefinition::STATUS_SCHEDULED : ScheduledTaskDefinition::STATUS_SKIPPED,
                ],
            ], Context::createDefaultContext());
        } catch (UniqueConstraintViolationException) {
            // this can happen if the function runs multiple times simultaneously
            // we just care that the task is registered afterward so we can safely ignore the error
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getUpdatePayload(ScheduledTaskEntity $registeredTask, ScheduledTask $task): array
    {
        $payload = [];
        if (!$task->shouldRun($this->parameterBag) && \in_array($registeredTask->getStatus(), [ScheduledTaskDefinition::STATUS_QUEUED, ScheduledTaskDefinition::STATUS_SCHEDULED], true)) {
            $payload['status'] = ScheduledTaskDefinition::STATUS_SKIPPED;
        }

        if ($task->shouldRun($this->parameterBag) && \in_array($registeredTask->getStatus(), [ScheduledTaskDefinition::STATUS_QUEUED, ScheduledTaskDefinition::STATUS_SKIPPED], true)) {
            $payload['status'] = ScheduledTaskDefinition::STATUS_SCHEDULED;
            $payload['nextExecutionTime'] = $this->calculateNextExecutionTime($registeredTask);
        }

        if ($task->getDefaultInterval() !== $registeredTask->getDefaultRunInterval()) {
            // default run interval changed
            $payload['defaultRunInterval'] = $task->getDefaultInterval();

            // if the run interval is still the default, update it to the new default
            if ($registeredTask->getRunInterval() === $registeredTask->getDefaultRunInterval()) {
                $payload['runInterval'] = $task->getDefaultInterval();
            }
        }

        if ($payload !== []) {
            $payload['id'] = $registeredTask->getId();
        }

        return $payload;
    }
}
