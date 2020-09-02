[titleEn]: <>(Asynchronous messaging)
[hash]: <>(article:internals_core_erd_framework_messagequeue)

[Back to modules](./../10-modules.md)

The message queue provides the necessary glue code between the API and the internally used message bus.

![Asynchronous messaging](./dist/erd-shopware-core-framework-messagequeue.png)


### Table `dead_message`

Failing messages in the queue. Re-queued with an ever-increasing threshold.


### Table `message_queue_stats`

The number of tasks currently in the queue.


### Table `scheduled_task`

Like cron jobs. Contains named messages and a an interval to execute them in.


[Back to modules](./../10-modules.md)
