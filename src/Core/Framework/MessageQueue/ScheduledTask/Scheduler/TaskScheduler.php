<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[Package('core')]
final class TaskScheduler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $scheduledTaskRepository,
        private readonly MessageBusInterface $bus,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    public function queueScheduledTasks(): void
    {
        $criteria = $this->buildCriteriaForAllScheduledTask();
        $context = Context::createDefaultContext();
        $tasks = $this->scheduledTaskRepository->search($criteria, $context)->getEntities();

        if (\count($tasks) === 0) {
            return;
        }

        // Tasks **must not** be queued before their state in the database has been updated. Otherwise,
        // a worker could have already fetched the task and set its state to running before it gets set to
        // queued, thus breaking the task.
        /** @var ScheduledTaskEntity $task */
        foreach ($tasks as $task) {
            $this->queueTask($task, $context);
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
                    RangeFilter::LT => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            ),
            new EqualsAnyFilter('status', [
                ScheduledTaskDefinition::STATUS_SCHEDULED,
                ScheduledTaskDefinition::STATUS_SKIPPED,
            ])
        );

        return $criteria;
    }

    private function queueTask(ScheduledTaskEntity $taskEntity, Context $context): void
    {
        $taskClass = $taskEntity->getScheduledTaskClass();

        if (!\is_a($taskClass, ScheduledTask::class, true)) {
            throw new \RuntimeException(sprintf(
                'Tried to schedule "%s", but class does not extend ScheduledTask',
                $taskClass
            ));
        }

        if (!$taskClass::shouldRun($this->parameterBag)) {
            $this->scheduledTaskRepository->update([
                [
                    'id' => $taskEntity->getId(),
                    'nextExecutionTime' => $this->calculateNextExecutionTime($taskEntity),
                    'status' => ScheduledTaskDefinition::STATUS_SKIPPED,
                ],
            ], $context);

            return;
        }

        $this->scheduledTaskRepository->update([
            [
                'id' => $taskEntity->getId(),
                'status' => ScheduledTaskDefinition::STATUS_QUEUED,
            ],
        ], $context);

        $task = new $taskClass();
        $task->setTaskId($taskEntity->getId());

        $this->bus->dispatch($task);
    }

    private function buildCriteriaForNextScheduledTask(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('status', [
                ScheduledTaskDefinition::STATUS_SCHEDULED,
                ScheduledTaskDefinition::STATUS_SKIPPED,
            ])
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

    private function calculateNextExecutionTime(ScheduledTaskEntity $taskEntity): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();

        $nextExecutionTimeString = $taskEntity->getNextExecutionTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $nextExecutionTime = new \DateTimeImmutable($nextExecutionTimeString);
        $newNextExecutionTime = $nextExecutionTime->modify(sprintf('+%d seconds', $taskEntity->getRunInterval()));

        return $newNextExecutionTime < $now ? $now : $newNextExecutionTime;
    }
}
