[titleEn]: <>(Console commands)
[hash]: <>(article:developer_console_commands)

## Console commands guide

To ease development tasks, Shopware contains the Symfony commands functionality.
This allows (plugin-) developers to define new commands executable via the
Symfony console at `bin/console`. The best thing about commands is, that they're
more than just simple standalone PHP scripts - they integrate into Symfony and
Shopware, so you've got access to all the functionality offered by both of them.

### Running commands

Commands are run via the `bin/console` executable. To list all available
commands, run `bin/console list`:

```text
$: php bin/console list
Symfony 4.4.4 (env: dev, debug: true)

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -e, --env=ENV         The Environment name. [default: "dev"]
      --no-debug        Switches off debug mode.
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal outp...

Available commands:
  about                                   Displays information about the curr...
  help                                    Displays help for a command
  list                                    Lists commands
 feature
  feature:dump                            Creating json file with feature con...
 assets
  assets:install                          Installs bundles web assets under a...
 bundle
  bundle:dump                             [administration:dump:plugins|admini...
 cache
  cache:clear                             Clears the cache
  cache:pool:clear                        Clears cache pools
  cache:pool:delete                       Deletes an item from a cache pool
  cache:pool:list                         List available cache pools
  cache:pool:prune                        Prunes cache pools
  cache:warmup                            Warms up an empty cache
 [...]
```

Each command usually has a namespace like `cache`, so to clear the cache you
would execute `php bin/console cache:clear`. If you'd like to learn more about
commands in general, have a look at
[this article](https://symfony.com/doc/current/console.html)
in the Symfony documentation.

### Adding commands via a plugin

Commands are recognised by Shopware, once they're tagged with the
`console.command` tag in the
[dependency injection container](https://symfony.com/doc/current/service_container.html)
. So to register a new command, just add it to your plugin's
[`services.xml`](./../20-developer-guide/40-services-subscriber.md)
and specify the `console.command` tag:

```xml
<services>
    <!-- ... -->

    <service id="Swag\ExamplePlugin\Command\ExampleCommand">
        <tag name="console.command"/>
    </service>
</services>

<!-- ... -->
```

Your command's class should extend from the
`Symfony\Component\Console\Command\Command` class, here's an example:

```php
// SwagExamplePlugin/src/Command/ExampleCommand.php

<?php declare(strict_types=1);

namespace Swag\ExamplePlugin\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExampleCommand extends Command
{
    protected static $defaultName = 'swag-commands:example';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('It works!');
    }
}
```

This command is of course only a basic example, so feel free to experiment. As
stated above, you now have access to all of the functionality offered by Symfony
and Shopware. For inspiration, maybe have a look at the Symfony documentation -
you may for example use
[tables](https://symfony.com/doc/current/components/console/helpers/table.html)
,
[progress bars](https://symfony.com/doc/current/components/console/helpers/progressbar.html)
, or
[custom formats](https://symfony.com/doc/current/components/console/helpers/formatterhelper.html)
.
