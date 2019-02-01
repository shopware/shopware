[titleEn]: <>(Plugin - Unit testing)
[wikiUrl]: <>(../plugin-system/unit-testing?category=shopware-platform-en/plugin-system)

In this guide, you will learn how to use `PHPUnit by Sebastian Bergmann` to test your plugin with `Shopware`.
Below you find an example file structure.

## Overview
```
└── SwagExample
    ├── SwagExample.php
    ├── Test
    │   └── ExampleTest.php
    ├── bin
    │   └── phpunit.sh
    └── phpunit.xml.dist
```
*Example File Structure*

## Creating a PHPUnit bash script

To run your plugin PHPUnit tests easily, you should create yourself a bash script.
As you can see above you got one inside the bin folder with the following content:

```bash
#!/usr/bin/env bash
./../../../vendor/bin/phpunit
```
*bin/phpunit.sh*

Alternatively, you could set up [Phpstorm with PHPUnit](https://www.jetbrains.com/help/phpstorm/using-phpunit-framework.html).

## Configuring PHPUnit

If you are not familiar with configuring PHPUnit you should start by reading this [guide](https://phpunit.de/manual/6.5/en/organizing-tests.html#organizing-tests.xml-configuration).
You need to configure PHPUnit inside the `phpunit.xml.dist` in your plugin root directory.
Below you can find an example.

````xml
<?xml version="1.0" encoding="UTF-8"?>

<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="http://schema.phpunit.de/7.1/phpunit.xsd"
         bootstrap="../../../tests/TestBootstrap.php">

    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="KERNEL_CLASS" value="Shopware\Development\Kernel"/>
        <env name="APP_ENV" value="test"/>
        <env name="APP_DEBUG" value="1"/>
        <env name="APP_SECRET" value="s$cretf0rt3st"/>
        <env name="SHELL_VERBOSITY" value="-1"/>
    </php>

    <testsuites>
        <testsuite name="SwagExample Testsuite">
            <directory>Test</directory>
        </testsuite>
    </testsuites>

    <filter>
        <whitelist>
            <directory suffix=".php">./</directory>
            <exclude>
                <directory suffix=".php">./Test</directory>
                <file>SwagExample.php</file>
            </exclude>
        </whitelist>
    </filter>

    <listeners>
        <listener class="Symfony\Bridge\PhpUnit\SymfonyTestsListener"/>
        <listener class="JohnKary\PHPUnit\Listener\SpeedTrapListener"/>
    </listeners>
</phpunit>
````
*phpunit.xml.dist*

Always set the bootstrap to `../../../src/TestBootstrap.php` which includes all `Shopware` dependencies.
The configurations inside the `<php></php>` tags set some `Shopware` relevant settings, like environment variables and server settings.

Define a test suite by giving it a name and a directory which holds your PHPUnit tests, in this case, all PHPUnit tests lay under `Test/`.
With filtering, you can restrict which files need testing.
In the above example every file with the `.php` extension, excluding `.php` files inside the `Test/` folder (your tests themselves) and the plugin base class `SwagExample.php` need testing.
This filtering is interesting for code coverage creation mostly.

## Test listeners

Test listeners are used to interact with tests in a special way. A test listener needs to implement the `PHPUnit\Framework\TestListenerInterface`.
The `SymfonyTestsListener` adds the `Symfony\Bridge\PhpUnit\Legacy\SymfonyTestsListenerTrait` to your test.
The [SpeedTrapListener](https://github.com/johnkary/phpunit-speedtrap) points out slow tests for you.

## Shopware Test Listeners

Below you'll find the listeners that ship with `Shopware` and what they are used for.

| class                                                               | usage                                          |
|---------------------------------------------------------------------|------------------------------------------------|
| Shopware\Core\Framework\Test\TestCaseBase\DatabaseCleanTestListener | Enable to see the db side effects of the tests |
| Shopware\Core\Framework\Test\TestCaseBase\TestValidityListener      | Enable to see Test structure violations.       |

## Adding a test

A test is a PHP class that inherits from `PHPUnit\Framework\TestCase`.
To create a new one simply put it in the directory you specified in your test suite.
In the above example, the destination for a new test would be `Test/`.
Below you find an example test showcasing some functionality.

```php
<?php declare(strict_types=1);

namespace SwagExample\Test;

use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use SwagExample\Service\ExampleService;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    private const EXAMPLE_SERVICE_ID = 'SwagExample\Service\ExampleService';

    use IntegrationTestBehaviour;

    public function test_if_plugin_manager_is_present(): void
    {
        $exampleService = $this->getContainer()->get(self::EXAMPLE_SERVICE_ID);

        self::assertInstanceOf(ExampleService::class, $exampleService);
    }
}
```
*Test/ExampleTest.php*

The above-shown test asserts whether or not the `ExampleService` is present in the DIC.
This doesn't test any plugin functionality but it helps to showcase the usage of the `Shopware` `IntegrationTestBehaviour` trait.
If you need access to the database, DIC, filesystem or cache use the `IntegrationTestBehaviour` trait.
This way you can access these things easily by calling one method, like the above `$this->getContainer()`.

## Running tests

As mentioned above, to run your test execute the shown bash script or run the test inside Phpstorm.
