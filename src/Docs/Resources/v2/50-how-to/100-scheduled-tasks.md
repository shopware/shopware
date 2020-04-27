[titleEn]: <>(Setting up a scheduled task)
[metaDescriptionEn]: <>(A scheduled task in Shopware 6 is mainly what people know as a 'cronjob'. If you were wondering how to set up such a scheduled task, then you've found the right article.)
[hash]: <>(article:how_to_scheduled_tasks)

## Overview

Quite often one might want to run any type of code on a regular basis, e.g. to clean up very old entries
every once in a while, automatically.
Formerly known as "Cronjobs", Shopware 6 supports a `ScheduledTask` for this.

A `ScheduledTask` and its respective `ScheduledTaskHandler` are registered in a plugin's `services.xml`.
For it to be found by Shopware 6 automatically, you need to place the `services.xml` file in a
`Resources/config/` directory, relative to the location of your plugin's base class.
The path could look like this: `<plugin root>/src/Resources/config/services.xml`, if you were to place your plugin's base class in
`<plugin root>/src`.

## Registering scheduled task in the DI container

Here's an example `services.xml` containing a new `ScheduledTask` as well as a new `ScheduledTaskHandler`:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\ScheduledTaskPlugin\ScheduledTask\MyTask">
            <tag name="shopware.scheduled.task" />
        </service>

        <service id="Swag\ScheduledTaskPlugin\ScheduledTask\MyTaskHandler">
            <argument type="service" id="scheduled_task.repository" />
            <tag name="messenger.message_handler" />
        </service>
    </services>
</container>
```

Note the tags required for both the task and its respective handler.
Your custom task will now be saved into the database once your plugin is activated.

## ScheduledTask and its handler

As you might have noticed, the `services.xml` file tries to find both the task itself as well as the new task handler in
a directory called `ScheduledTask`.
This naming is up to you, Shopware 6 decided to use this name though.

Here's the mentioned example `ScheduledTask` file in `<plugin root>/src/ScheduledTask/`:
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

Make sure to add a vendor prefix to your custom task, to prevent collisions with other plugin's scheduled tasks.

Following will be the respective task handler:
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

Now every five minutes, your task will be executed and it will print an output every time now.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-scheduled-task-plugin).
