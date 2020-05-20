[titleEn]: <>(Migrations)
[hash]: <>(article:developer_migrations)

## Migrations guide

Migrations are php classes containing database schema changesets. These
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
