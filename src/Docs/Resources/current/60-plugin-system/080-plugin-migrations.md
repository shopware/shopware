[titleEn]: <>(Migrations)
[wikiUrl]: <>(../plugin-system/migrations?category=shopware-platform-en/plugin-system)

In this guide, you will learn what migrations are and how to use them.
Migrations are PHP classes used to manage incremental and reversible database schema changes.
`Shopware` comes with a pre-built `Migration System`, to take away most of the work for you.
Throughout this guide, you will find the `$` symbol representing your command line.
At the end of this guide, you find the example as download.

## Overview
Your plugin migrations lay under the `Migration` directory in your plugin root:
```
└── plugins
    └── SwagExample
        ├── Migration
        │   └── Migration1546422281AddGeneralSettingsTable.php
        └── SwagExample.php
```
*File structure*

As you can see there is one file in the `Migration` directory. Below you find a break down of what each part means.

| File Name Snippet       | Meaning                                                   |
|-------------------------|-----------------------------------------------------------|
| Migration               | Each migration file has to start with Migration           |
| 1546422281              | A Timestamp used to make migrations incremental           |
| AddGeneralSettingsTable | A descriptive name for your migration                     |
| .php                    | PHP file extension                                        |

*You don't need to remember all this or create it by hand since there is a console command to create a new migration.*

## Creating A Migration
To create a new migration for your plugin, open your `Shopware` root directory in your terminal.
The command to create a new migration for your plugin is the `database:create-migration` command.
Below you can see the command used in this example to create the migration seen above in the file structure.

```
$ ./bin/console database:create-migration -p SwagExample --name AddGeneralSettingsTable
```

Below you'll find a break down of the command.

| Command Snippet                | Meaning                                                          |
|--------------------------------|------------------------------------------------------------------|
| ./bin/console                  | Calls the executable Symfony console application                 |
| database:create-migration      | The command to create a new migration                            |
| -p *your_plugin_name*          | -p creates a new migration for the plugin with the name provided |
| --name *your_descriptive_name* | Appends the provided string after the timestamp                  |

*Please note, if you create a new migration yourself the timestamp will vary.*

If you take a look at your created migration it should look similar to this:
```php
<?php declare(strict_types=1);

namespace SwagExample\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546422281AddGeneralSettingsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546422281;
    }

    public function update(Connection $connection): void
    {
        // implement update
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
```
*Migration/Migration1546422281AddGeneralSettingsTable.php*

As you can see your migration contains 3 methods:
* `getCreationTimestamp()`
* `update()`
* `updateDestructive()`

There is no need to change `getCreationTimestamp()`, it returns the timestamp that's also in the file name.
In the `update()` method you implement nondestructive changes. In other words, the `update()` method should always be reversible.
The `updateDestructive()` method is the counterpart to `update()` and used for destructive none reversible changes,
like dropping columns or tables.
Below you find an example of a nondestructive migration, creating a new table for your plugin.

```php
<?php declare(strict_types=1);

namespace SwagExample\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546422281AddGeneralSettingsTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546422281;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
CREATE TABLE IF NOT EXISTS `swag_example_general_settings` (
    `id`                INT             NOT NULL,
    `example_setting`   VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;

        $connection->executeQuery($query);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
```
*Migration/Migration1546422281AddGeneralSettingsTable.php*

## Running Migrations
As you install your plugin, the `Migration` directory is added to the `MigrationCollection` and all migrations get executed.
Also if you update a plugin via the `Plugin Manager` all new migrations are executed.
If you want to execute a migration by hand as part of your development process, simply create it after you installed your plugin.
This way your plugin `Migration` directory is already registered during the install process and you can run any new created migration by hand,
using one of the following commands. If you lost track or want to debug an error take a look into the `migration` table in your database.

| Command                      | Arguments  | Usage                                                                    |
|------------------------------|------------|--------------------------------------------------------------------------|
| database:migrate             | identifier (optional) | Calls the update() methods of unhandled migrations            |
| database:migrate-destructive | identifier (optional) | Calls the updateDestructive() methods of unhandled migrations |

*If you want to see the usage of a command you can run: `./bin/console command:of:interest` without any arguments*

The `identifier` argument is used to decide which migrations should be executed.
Per default, the `identifier` is set to run `Shopware Core` migrations.
To run your plugin migrations set the `identifier` argument to your plugin identifier, in this example `SwagExample`.
If you want to be more specific, provide the migration namespace of your plugin as the `identifier` argument.
Below you can find a few working example commands.

```
$ ./bin/console database:migrate SwagExample --all
$ ./bin/console database:migrate --all SwagExample

$ ./bin/console database:migrate --all SwagExample\\Migration
$ ./bin/console database:migrate SwagExample\\Migration --all
```

## Download
Here you can *Download Link Here* the above-shown example.