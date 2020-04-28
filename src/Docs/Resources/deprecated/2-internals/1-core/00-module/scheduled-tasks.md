[titleEn]: <>(Scheduled Tasks)
[hash]: <>(article:scheduled_tasks)

Scheduled Tasks are a way to to add recurring tasks to the systems. These tasks will run in an defined interval. Scheduled Tasks work asynchronously over the message queue.

## Adding a scheduled task

To add your scheduled task create a class that extend the abstract `ScheduledTask`-class and implement the necessary methods.
```php
<?php declare(strict_types=1);

namespace Swag\ScheduledTaskPlugin\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class MyTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'vendor_prefix.my_task';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // 5 minutes
    }
}
```
**Note:** As the task will be dispatched to the MessageQueue it has to be serializable.

After that you can register your scheduledTask in your `services.xml` and tag it with the `shopware.scheduled.task`-tag.
```xml
<!-- services.xml -->
<services>
    <service id="Swag\ScheduledTaskPlugin\ScheduledTask\MyTask">
        <tag name="shopware.scheduled.task" />
    </service>
</services>
```

After activating your Plugin this task will be registered in the Database. The `TaskScheduler` will then dispatch the scheduled task in the defined interval to the Message Queue.
So a scheduled task is basically just a message that gets dispatched in a regular interval.

## Handling a scheduled task

As a scheduled task is just a message, you have to create a message handler that handles your scheduled task and register it in the container with the `messenger.message_handler`-tag.
```php
<?php declare(strict_types=1);

namespace Swag\ScheduledTaskPlugin\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;

class MyTaskHandler extends ScheduledTaskHandler
{
    public static function getHandledMessages(): iterable
    {
        return [ MyTask::class ];
    }

    public function run(): void
    {
        echo 'Do stuff!';
    }
}
```

```xml
<!-- services.xml -->
<services>
    <service id="Swag\ScheduledTaskPlugin\ScheduledTask\MyTaskHandler">
        <argument type="service" id="scheduled_task.repository" />
        <tag name="messenger.message_handler" />
    </service>
</services>
```

## The Task Scheduler

The `TaskScheduler` is responsible for dispatching all scheduled tasks regularly into the queue. To do this it has to be run periodically.
In the default configuration this is done via the AdminWorker, however this is not the best solution and should not be used in production environments, as described in the MessageQueue docs.
The preferred way is to use the cli-worker.

### Configuring the cli-worker

You can configure the command just to run a certain amount of time or to stop if it exceeds a certain memory limit like:
```bash
bin/console scheduled-task:run --time-limit=60
```
```bash
bin/console scheduled-task:run --memory-limit=128M
```

Just like the MessageQueueConsumer you should use the limit option to periodically restart the worker processes, because of the memory leak issues of long running php processes.
To automatically start the processes again after they stopped because of exceeding the given limits you can use something like [upstart](http://upstart.ubuntu.com/getting-started.html) or [supervisor](http://supervisord.org/running.html).
Alternatively you can configure a `CronJob` that runs the command again shortly after the time limit is exceeded.

If you have configured the cli-worker, you can turn off the admin worker in your `shopware.yaml`.
```yaml
# config/packages/shopware.yaml
shopware:
    admin_worker:
        enable_admin_worker: false
``` 
**Note:** This will disable the AdminWorker completely and you have to configure the cli-worker for scheduled tasks as well.

The cli workers can be executed with the `messenger:consume` command. 

### Debugging scheduled tasks
After the scheduled task is registered in the system, it can be debugged as follows:

* Start the scheduled task runner in the shell: `php bin/console scheduled-task:run`.
* Start the cli worker for the command queue: `php bin/console messenger:consume`

All debug statements `(dump(), print_r(), ...)` now appear in the shell of the `messenger:consume` commands 
