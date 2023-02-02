[titleEn]: <>(Migrations)
[hash]: <>(article:developer_migrations)

## Migrations guide

Migrations are PHP classes containing database schema changesets. These
changesets can be applied or reverted to bring the database into a certain
state. You might know the concept of migrations from other Frameworks or
Symfony as well. Read on to find out how to add your own migrations to
Shopware when implementing a plugin.

### Adding your own migrations

For Shopware to recognise additional plugin migrations, they need to be placed
in the `Migration` directory under your plugin's source code root directory:

```
./
+-- SwagExamplePlugin/
    +-- src/
        +-- Migration/
            +-- # Place migrations here
        +-- SwagExamplePlugin.php
```

Each migration filename follows a pattern described
[here](./../60-references-internals/40-plugins/080-plugin-migrations.md#overview)
. To ease plugin development, Shopware provides a console command which can be
used to generate a correctly named migration file with the default methods
needed. You may use this command to create a migration for your plugin:

```bash
php bin/console database:create-migration \
  -p SwagExamplePlugin \
  --name CreateSwagEntity
```

After running this command, you should find a new migration file under your
plugins source code root directory:

```
./
+-- SwagExamplePlugin/
    +-- src/
        +-- Migration/
            +-- Migration1587651945CreateSwagEntity.php
        +-- SwagExamplePlugin.php
```

### Modifying the database

Now that you've created the migration file, open it in your editor or IDE. There
should already be two methods written out, namely `update` and
`updateDestructive`. The `update` method may contain only non-destructive
changes which can be rolled back at any time. The `updateDestructive` method
should contain destructive changes, which cannot be reversed, like dropping
columns or tables.

Let's create a table for testing out the migration, you can use the following
example implementation for this:

```php
public function update(Connection $connection): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `swag_example_entity` (
    `id`   BINARY(16)                              NOT NULL,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL
  PRIMARY KEY (`id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    
    $connection->executeUpdate($sql);
}
```

### Running the migration

For testing purposes, you can apply the new migration using
[this console command](./../60-references-internals/40-plugins/080-plugin-migrations.md#running-migrations-manually)
:

```bash
php bin/console database:migrate --all SwagExamplePlugin
```

If you now have a look at your database, the new `swag_example_entity` table
should have been created.

From now on, this migration will be automatically applied when the plugin is
installed, but you may also
[control this behaviour](./../60-references-internals/40-plugins/080-plugin-migrations.md#advanced-migration-control)
in detail if you wish to.


### Important Rules

To ensure the stability of updates and Shopware itself, it is imperative that the following rules are always followed when creating new migrations.


##### 1. Migrations must be able to be executed more than once
If a migration fails, make sure that it can be executed again. A migration should check whether structures have already been created to avoid creating duplicates.


##### 2. Do not trust any identifier
Identifiers on a customer system can always be different from those on a development environment, for example.
Search in detail for trusted identifiers. If in doubt, a database query for the identifier must be initiated in advance.


##### 3. Do not trust customer data
Customers sometimes produce very complex and unpredictable data constructs. Therefore, never rely on the existence of data or structures. Always program migrations very defensively with exact queries on the situation.


##### 4. Duration (Performance)
A migration must never take longer than 10 seconds on your local system. You do not know the timeout values of the customers, so this value should never be exceeded. Customer systems are often slower than developer systems, but contain a lot of data. Make sure to test your migration with big data sets.


##### 5. Don't hurt customized Data
There are data that are often individualized by customers. Under no circumstances may a migration overwrite individualized customer data. Always check this in your migration.


##### 6. There are no default languages
The customers can select any language as their default. Don't rely on any language as given, neither English nor German.


##### 7. Test data migration
If data is updated in a migration, a test must be written for this migration.


##### 8. NEVER update a migration
You cannot alter a migration which was already part of a released version.
You will need to write a new migration to do the changes, otherwise an existing system will not get the same data structure on update as a new installation.
The only exception to this rule is when the migration throws an error.
