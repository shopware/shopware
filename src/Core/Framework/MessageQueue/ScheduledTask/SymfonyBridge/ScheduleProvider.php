<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask\SymfonyBridge;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * @experimental stableVersion:v6.7.0 feature:SYMFONY_SCHEDULER
 */
#[Package('core')]
class ScheduleProvider implements ScheduleProviderInterface
{
    /**
     * @internal
     *
     * @param iterable<int, ScheduledTask> $tasks
     */
    public function __construct(
        private readonly iterable $tasks,
        private readonly Connection $connection,
    ) {
    }

    public function getSchedule(): Schedule
    {
        /** @var array<string, array{run_interval: int, status: string}> $dbConfigs */
        $dbConfigs = $this->connection->fetchAllAssociativeIndexed(
            'SELECT name, run_interval, status FROM scheduled_task'
        );

        $schedule = new Schedule();
        foreach ($this->tasks as $task) {
            $name = $task::getTaskName();

            if (\array_key_exists($name, $dbConfigs) && $dbConfigs[$name]['status'] === ScheduledTaskDefinition::STATUS_INACTIVE) {
                continue;
            }

            $interval = $dbConfigs[$name]['run_interval'] ?? $task::getDefaultInterval();
            $schedule->add(
                RecurringMessage::every($interval, $task)
            );
        }

        return $schedule;
    }
}
