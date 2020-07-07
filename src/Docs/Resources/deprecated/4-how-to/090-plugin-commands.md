[titleEn]: <>(Creating commands via plugin)
[metaDescriptionEn]: <>(Sometimes you want to bring some helper commands with your plugin. Here you'll learn how that's done.)
[hash]: <>(article:how_to_plugin_commands)

## Overview

Creating a command for Shopware 6 via a plugin works exactly like you would add a command to Symfony.
Make sure to have a look at the [Symfony commands guide](https://symfony.com/doc/current/console.html#registering-the-command).

## Registering your command

The main requirement here is to have a `services.xml` file loaded in your plugin.
This can be achieved by placing the file into a `Resources/config` directory relative to your plugin's base class location.
Make sure to also have a look at the method [getServicesFilePath](./../2-internals/4-plugins/020-plugin-base-class.md#getServicesFilePath)

From here on, everything works exactly like in Symfony itself.

Here's an example `services.xml` which registers your custom command:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="Swag\PluginCommands\Command\ExampleCommand">
            <tag name="console.command"/>
        </service>
    </services>
</container>
```

And the related example command:
```php
<?php declare(strict_types=1);

namespace Swag\PluginCommands\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends Command
{
    protected static $defaultName = 'plugin-commands:example';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Do something...');
    }
}
```

In this example, it would be located in the directory `<plugin root>/src/Command`.
After installing, you can now execute your command by running this command: `bin/console plugin-commands:example`

Make sure to read the full guide about [Symfony commands](https://symfony.com/doc/current/console.html) to understand, how to deal with commands and how they can be configured.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-plugin-commands).
