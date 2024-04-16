<?php

namespace Shopware\Core\Framework\Api\HealthCheck\Service\Check;

use Shopware\Core\Framework\Api\HealthCheck\Model\Result;
use Shopware\Core\Framework\Api\HealthCheck\Model\Status;
use Shopware\Core\Framework\Api\HealthCheck\Service\Check;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;

class ScheduledTasks implements Check
{
    public function __construct(
        private readonly EntityRepository $scheduledTaskRepository
    )
    {
    }

    public function run(): Result
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('status', ScheduledTaskDefinition::STATUS_FAILED)
        );

        $failedCount = $this->scheduledTaskRepository->search($criteria, Context::createDefaultContext())->count();
        if ($failedCount > 0) {
            return new Result('ScheduledTasks', Status::Error, 'There are failed scheduled tasks');
        }

        // check for overdue tasks (some calculation against intervals... and last execution time (x3 maybe?)...)
        $overdueTasks = $this->getOverdueTasks();
        if (! empty($overdueTasks)) {
            return new Result('ScheduledTasks', Status::Error, 'There are overdue scheduled tasks');
        }

        return new Result('ScheduledTasks', Status::Healthy);
    }


    public function getOverdueTasks()
    {
        return [];
    }

    public function dependsOn(): array
    {
        return [Database::class];
    }
}
