[titleEn]: <>(Creating commands via plugin)

## Overview

Creating a command for the Shopware platform via a plugin works exactly like you would add a command to Symfony.
Make sure to have a look at the [Symfony commands guide](https://symfony.com/doc/current/console.html#registering-the-command).

The only difference here is, that you need to let the Shopware platform know about your plugin's custom `services.xml` location,
in which you register your plugin's command.

This is done in the plugin's base class.

## Plugin base class

You need to overwrite the base class' `build` method to load your `services.xml` file.

```php
<?php declare(strict_types=1);

namespace PluginCommands;

use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Config\FileLocator;

class PluginCommands extends Plugin
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

From here on, everything works exactly like in Symfony itself.

Here's an example `services.xml`:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>
        <service id="PluginCommands\Command\ExampleCommand">
            <tag name="console.command"/>
        </service>
    </services>
</container>
```

And the related example command:
```php
<?php declare(strict_types = 1);

namespace PluginCommands\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin-commands:example');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Do something...');
    }
}
```

After installing, you can now execute your command by running this command: `bin/console plugin-commands:example`

Make sure to read the full guide about [Symfony commands](https://symfony.com/doc/current/console.html) to understand, how to deal with commands and how they can be configured.