[titleEn]: <>(Asynchronous messaging)

[Back to modules](./../10-modules.md)

The message queue provides the necessary glue code between the API and the internally used message bus.

![Asynchronous messaging](./dist/erd-shopware-core-framework-messagequeue.png)


### Table `dead_message`

Failing messages in the queue. Requeued with an ever increasing threshold.


### Table `message_queue_stats`

The number of tasks currently in the queue.


[Back to modules](./../10-modules.md)
