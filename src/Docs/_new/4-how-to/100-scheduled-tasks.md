[titleEn]: <>(Setting up a scheduled task)

## Overview

Quite often one might want to run any type of code on a regular basis, e.g. to clean up very old entries
every once in a while, automatically.
Formerly known as "Cronjobs", the Shopware platform supports a `ScheduledTask` for this.

A `ScheduledTask` and it's respective `ScheduledTaskHandler` are registered in a plugin's `services.xml`.

## Plugin base class

You need to overwrite your plugin's base class' `build` method to load your `services.xml` file.

```php
<?php declare(strict_types=1);

namespace ScheduledTaskPlugin;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class ScheduledTaskPlugin extends Plugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }
}
```

It now tries to load the `services.xml` file inside the `<plugin-root>/DependencyInjection` directory.

## Services.xml

Here's an example `services.xml` containing a new `ScheduledTask` as well as a new `ScheduledTaskHandler`:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="ScheduledTaskPlugin\ScheduledTask\MyTask">
            <tag name="shopware.scheduled.task" />
        </service>

        <service id="ScheduledTaskPlugin\ScheduledTask\MyTaskHandler">
            <argument type="service" id="scheduled_task.repository" />
            <tag name="messenger.message_handler" />
        </service>
    </services>
</container>
```

Note the tags required for both the task and it's respective handler.
Your custom task will now be saved into the database once your plugin is activated.

## ScheduledTask and it's handler

As you might have noticed, the `services.xml` file tries to find both the task itself as well as the new task handler in
a directory called `ScheduledTask`.
This naming is up to you, the Shopware platform decided to use this name though.

Here's the mentioned example `ScheduledTask` file:
```php
<?php declare(strict_types=1);

namespace ScheduledTaskPlugin\ScheduledTask;

use Shopware\Core\Framework\ScheduledTask\ScheduledTask;

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

namespace ScheduledTaskPlugin\ScheduledTask;

use Shopware\Core\Framework\ScheduledTask\ScheduledTaskHandler;

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