[titleEn]: <>(Automated tests in plugins)
[metaDescriptionEn]: <>(To ensure your plugin's functionality, it's highly recommended to automatically test your source code. For this purpose, you can easily setup a PHPUnit testing environment for plugins.)
[hash]: <>(article:how_to_plugin_testing)

## Overview

To ensure your plugin's functionality, it's highly recommended to automatically test your source code.
For this purpose, you can easily setup a [PHPUnit](https://phpunit.readthedocs.io/en/8.0/writing-tests-for-phpunit.html) testing environment for plugins.

This quick HowTo requires you to have a proper working plugin first.

## Setup

Basically, the following is just a suggestion on how to setup PHPUnit with your plugin.
This is by no means a hard requirement, feel free to create your own testing suite.

## PHPUnit config file

PHPUnit is configured using a `phpunit.xml.dist` file.
Read more about the possible configurations of the `phpunit.xml.dist` file [here](https://phpunit.readthedocs.io/en/8.0/configuration.html?highlight=.xml).

Here's what your plugin's `phpunit.xml.dist` file could look like:

```xml
<?xml version="1.0" encoding="UTF-8"?>

<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="http://schema.phpunit.de/7.1/phpunit.xsd"
         bootstrap="../../../vendor/shopware/platform/src/Core/TestBootstrap.php"
         cacheResult="false">

    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="KERNEL_CLASS" value="Shopware\Development\Kernel"/>
        <env name="APP_ENV" value="test"/>
        <env name="APP_DEBUG" value="1"/>
        <env name="APP_SECRET" value="s$cretf0rt3st"/>
        <env name="SHELL_VERBOSITY" value="-1"/>
    </php>

    <testsuites>
        <testsuite name="Example Testsuite">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist>
            <directory suffix=".php">./</directory>
        </whitelist>
    </filter>
</phpunit>
```

You're also free to add and remove configurations, so the testsuite perfectly fits your needs.
Important to note is the `bootstrap` configuration in the `phpunit` element.
In this example, you're required to put your tests into the `tests` directory.

Here's an example test, which simply tries to instantiate every `.php` class, to see if any used core classes
went missing:
```php
<?php declare(strict_types=1);

namespace Swag\PluginTestingTests;

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class UsedClassesAvailableTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testClassesAreInstantiable(): void
    {
        $namespace = str_replace('\Test', '', __NAMESPACE__);

        foreach ($this->getPluginClasses() as $class) {
            $classRelativePath = str_replace(['.php', '/'], ['', '\\'], $class->getRelativePathname());

            $this->getMockBuilder($namespace . '\\' . $classRelativePath)
                ->disableOriginalConstructor()
                ->getMock();
        }

        // Nothing broke so far, classes seem to be instantiable
        $this->assertTrue(true);
    }

    private function getPluginClasses(): Finder
    {
        $finder = new Finder();
        $finder->in(realpath(__DIR__ . '/../'));
        $finder->exclude('Test');
        return $finder->files()->name('*.php');
    }
}
```

Make sure to have a look at the `IntegrationTestBehaviour` trait, which comes in with some handy features,
such as automatically setting up a database transaction or clearing the cache before starting your tests.

## Executing the tests

For easier usage, you could create a batch file called `phpunit.sh` into a `bin` directory of your plugin.
Its only purpose then would be executing the `PHPUnit` testsuite.
Make sure the path in the following file actually fits.

```sh
#!/usr/bin/env bash
./../../../vendor/bin/phpunit
```

Now you can simply run `bin/phpunit.sh` inside your plugin root directory to execute your plugin's tests.
Also make sure to have a look at the [Symfony PHPUnit documentation](https://symfony.com/doc/current/testing.html).

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-plugin-testing).


## Production template

If you use the production template (which is also used by the zipped download version), you have to change a few things.

First the path to the bootstrap file is different, because there's no `shopware/platform`, but `shopware/core`.
So we have to change `vendor/shopware/platform/src/Core/TestBootstrap.php` to `vendor/shopware/core/TestBootstrap.php`.

We also need to change the `KERNEL_CLASS` from `Shopware\Development\Kernel` to `Shopware\Production\Kernel`.

An example config:

```xml
<?xml version="1.0" encoding="UTF-8"?>

<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="http://schema.phpunit.de/7.1/phpunit.xsd"
         bootstrap="../../../vendor/shopware/core/TestBootstrap.php"
         cacheResult="false">

    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="KERNEL_CLASS" value="Shopware\Production\Kernel"/>
        <env name="APP_ENV" value="test"/>
        <env name="APP_DEBUG" value="1"/>
        <env name="APP_SECRET" value="s$cretf0rt3st"/>
        <env name="SHELL_VERBOSITY" value="-1"/>
    </php>

    <testsuites>
        <testsuite name="Example Testsuite">
            <directory>tests/</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist>
            <directory suffix=".php">./</directory>
        </whitelist>
    </filter>
</phpunit>
```
### Database setup

Additionally, you have to create a database with the name `$DBNAME_test`.
For example if your database is `shopware` the tests will use the `shopware_test` database.

This database needs to be set up with basic data. This can accomplished by
running `DATABASE_URL=$myconnectionstring_test bin/console system:install --basic-data` or
by coping a clean working database.
