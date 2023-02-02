[titleEn]: <>(Updating your plugin via migrations)
[metaDescriptionEn]: <>(Whenever you decide to release a new version of your plugin, including new features, you might have to take care of new database tables or about updating existing ones. This can be done using the Migration system in your plugin.)
[hash]: <>(article:how_to_plugin_migrations)

## Overview

Whenever you decide to release a new version of your plugin, including new features, you might have to take
care of new database tables or about updating existing ones.
This also includes checking, if an update was already applied, mostly done so by including a multitude of
version checks into your plugin's `update` method.
As you might notice, this will bloat the `update` method sooner or later, becoming more and more of a pain to
maintain reliably.

A very common solution to this issue is a database migration system, which Shopware 6 supports out of the box
for every plugin.

Here's a brief introduction on how to use `Migrations` in your plugin.
Make sure to have a look at our in-depth guide about [plugin migrations](./../2-internals/4-plugins/080-plugin-migrations.md).

## Setup

This example won't explain how to create a plugin in the first instance.
How to create your first plugin is explained in detail [here](./../2-internals/4-plugins/010-plugin-quick-start.md).

By default, Shopware 6 is looking for migration files in a folder called `Migration` relative to your plugin's base class directory.
You can adjust this behaviour by overwriting the [getMigrationNamespace()](./../2-internals/4-plugins/020-plugin-base-class.md#getMigrationNamespace) method in your plugin.

## The migration file

For this example, the default folder `Migration` is used to add a migration.
The following PHP file should be named something like this: `Migration1546422281ExampleDescription.php`

So, here's how an example migration could look like:
```php
<?php declare(strict_types=1);

namespace Swag\PluginMigrationExample\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1546422281ExampleDescription extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1546422281;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
CREATE TABLE IF NOT EXISTS `plugin_migration_example_general_settings` (
    `id`                INT             NOT NULL,
    `example_setting`   VARCHAR(255)    NOT NULL,
    PRIMARY KEY (id)
)
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;

        $connection->executeUpdate($query);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
```

## Generating migration
There are two commands available which could help you writing your migration file.

### Migration file
First you should create the migration class with `bin/console database:create-migration --plugin PluginMigrationExample --name ExampleDescription`.
This command will create a class with a unique timestamp and the two update methods.

### SQL schema
After that you could execute the following command: `bin/console dal:create:schema`
This command selects all active entity definitions known to Shopware and tries to create SQL queries on base of the fields.
*Note: Your plugin has to be activated, otherwise your custom entity definition will not be considered.*
The queries are outputted into `/schema`.
Search for the correct SQL file and copy the query to your migration file. 
*Note: This command is in beta state and should not be used blindly*
Double check if the generated SQL query really fits your needs.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-plugin-migration-example).
