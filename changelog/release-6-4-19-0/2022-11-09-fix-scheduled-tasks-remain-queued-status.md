---
title: Fix scheduled tasks remain queued status
issue: NEXT-23406
---
# Core
* Changed method `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry::registerTasks` to update `scheduled` or `queued` status tasks to `skipped` if it should not run anymore
* Changed method `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry::registerTasks` to update `skipped` or `queued` status tasks to `scheduled` if it should run again
* Changed method `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler::queueScheduledTasks` to change status of `scheduled` tasks to `skipped` if it should not run anymore
* Added a new migration `\Shopware\Core\Migration\V6_4\Migration1667983492UpdateQueuedTasksToSkipped` to update invalid queued tasks status to `skipped`
