---
title: Update ScheduledTask run interval after plugin update
issue: NEXT-25125
---
# Core
* Added `defaultRunInterval` to `ScheduledTask` entity, which will be required in v6.6.0.0.
* Changed `\Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry::registerTasks` to update the `defaultRunInterval` and the `runInterval` (if the runInterval was not changed manually) of a scheduled task when they have changed.
___
# Next Major Version Changes
## `defaultRunInterval` field is required for `ScheduledTask` entities

The `defaultRunInterval` field is now required for `ScheduledTask` entities. So you now have to provide the following required fields to create a new Scheduled Task in the DB:
* `name`
* `scheduledTaskClass`
* `runInterval`
* `defaultRunInterval`
* `status`
