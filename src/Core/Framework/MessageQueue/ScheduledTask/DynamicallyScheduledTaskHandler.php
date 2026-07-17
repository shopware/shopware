<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\ScheduledTask;

use Shopware\Core\Framework\Log\Package;

/**
 * Implemented by {@see ScheduledTaskHandler}s that compute their own next execution time from domain data
 * (e.g. the timestamp of the next pending record) instead of the default `now + runInterval` schedule.
 *
 * The {@see ScheduledTaskExecutor} asks the handler for the next execution time and persists it, so the
 * handler only answers the "when", not the "how".
 */
#[Package('framework')]
interface DynamicallyScheduledTaskHandler
{
    /**
     * Return the time the task should next run at, or null to fall back to the default `now + runInterval` schedule.
     */
    public function getNextExecutionTime(ScheduledTask $task, ScheduledTaskEntity $taskEntity): ?\DateTimeInterface;
}
