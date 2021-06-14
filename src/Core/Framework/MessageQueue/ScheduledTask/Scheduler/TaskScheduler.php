<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Symfony\Component\Messenger\MessageBusInterface;

class TaskScheduler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $scheduledTaskRepository;

    /**
     * @var MessageBusInterface
     */
    private $bus;

    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        MessageBusInterface $bus
    ) {
        $this->scheduledTaskRepository = $scheduledTaskRepository;
        $this->bus = $bus;
    }

    public function queueScheduledTasks(): void
    {
        $criteria = $this->buildCriteriaForAllScheduledTask();
        $tasks = $this->scheduledTaskRepository->search($criteria, Context::createDefaultContext())->getEntities();

        if (\count($tasks) === 0) {
            return;
        }

        $updatePayload = [];
        /** @var ScheduledTaskEntity $task */
        foreach ($tasks as $task) {
            $updatePayload[] = [
                'id' => $task->getId(),
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
            ];
        }

        $this->scheduledTaskRepository->update($updatePayload, Context::createDefaultContext());

        // Tasks **must not** be queued before their state in the database has been updated. Otherwise
        // a worker could have already fetched the task and set its state to running before it gets set to
        // queued, thus breaking the task.
        /** @var ScheduledTaskEntity $task */
        foreach ($tasks as $task) {
            $this->queueTask($task);
        }
    }

    public function getNextExecutionTime(): ?\DateTimeInterface
    {
        $criteria = $this->buildCriteriaForNextScheduledTask();
        /** @var AggregationResult $aggregation */
        $aggregation = $this->scheduledTaskRepository
            ->aggregate($criteria, Context::createDefaultContext())
            ->get('nextExecutionTime');

        /** @var MinResult $aggregation */
        if (!$aggregation instanceof MinResult) {
            return null;
        }
        if ($aggregation->getMin() === null) {
            return null;
        }

        return new \DateTime((string) $aggregation->getMin());
    }

    public function getMinRunInterval(): ?int
    {
        $criteria = $this->buildCriteriaForMinRunInterval();
        $aggregation = $this->scheduledTaskRepository
            ->aggregate($criteria, Context::createDefaultContext())
            ->get('runInterval');

        /** @var MinResult $aggregation */
        if (!$aggregation instanceof MinResult) {
            return null;
        }
        if ($aggregation->getMin() === null) {
            return null;
        }

        return (int) $aggregation->getMin();
    }

    private function buildCriteriaForAllScheduledTask(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new RangeFilter(
                'nextExecutionTime',
                [
                    RangeFilter::LT => (new \DateTime())->format(\DATE_ATOM),
                ]
            ),
            new EqualsFilter('status', ScheduledTaskDefinition::STATUS_SCHEDULED)
        );

        return $criteria;
    }

    private function queueTask(ScheduledTaskEntity $taskEntity): void
    {
        $taskClass = $taskEntity->getScheduledTaskClass();

        if (!\is_a($taskClass, ScheduledTask::class, true)) {
            throw new \RuntimeException(sprintf(
                'Tried to schedule "%s", but class does not extend ScheduledTask',
                $taskClass
            ));
        }

        $task = new $taskClass();
        $task->setTaskId($taskEntity->getId());

        $this->bus->dispatch($task);
    }

    private function buildCriteriaForNextScheduledTask(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('status', ScheduledTaskDefinition::STATUS_SCHEDULED)
        )
        ->addAggregation(new MinAggregation('nextExecutionTime', 'nextExecutionTime'));

        return $criteria;
    }

    private function buildCriteriaForMinRunInterval(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsFilter('status', ScheduledTaskDefinition::STATUS_INACTIVE),
            ])
        )
        ->addAggregation(new MinAggregation('runInterval', 'runInterval'));

        return $criteria;
    }
}
