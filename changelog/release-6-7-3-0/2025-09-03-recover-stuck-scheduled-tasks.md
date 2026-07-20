---
title: Recover stuck scheduled tasks
---
# Core
* Changed `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler::queueScheduledTasks()` to also queue tasks that are stuck for over 12 hours in `queued` or `running` state. Thus the scheduled tasks will automatically recover in case the message got dropped or the worker crashed while executing the task. 
* Deprecated `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler::getNextExecutionTime()` as it is not used anymore.
___ 
# Update Information
## Recovery of stuck scheduled tasks
Scheduled tasks that are stuck in `queued` or `running`state for over 12 hours will be automatically recovered and re-queued.
In rare circumstances when the queue is overloaded, this might lead to the task being scheduled multiple times, however most of the time it is safe to assume that the message was lost if it was not received after 12 hours.
When you have tasks that run longer than 12 hours, you can override the requeue timeout over the `shopware.messenger.scheduled_task.requeue_timeout` configuration.

## Deprecated `TaskScheduler::getNextExecutionTime()`
The `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler::getNextExecutionTime()` method is not used anymore and will be removed in 6.8.0.
___
# Next Major Version Changes
## Removed `TaskScheduler::getNextExecutionTime()`
The `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler::getNextExecutionTime()` method was not used anymore and was removed.
