[titleEn]: <>(Migrations)
[hash]: <>(article:developer_migrations)

## Migrations guide

Migrations are php classes containing database schema changesets. These
changesets can be applied or reverted to bring the database into a certain
state. You might know the concept of migrations from other Frameworks or
Symfony as well. Read on to find out how to add your own migrations to
Shopware when implementing a plugin.

### Adding your own migrations

For Shopware to recognise additional plugin migrations, they need to be in the
`Migration` directory under your plugin's source code root directory:

```
./
+-- AcmeExamplePlugin/
    +-- src/
        +-- Migration/
            +-- Migration1587651945ExampleMigration.php
        +-- AcmeExamplePlugin.php
```

#### Migration names

A migration in Shopware is named using a combination of multiple values:

`Migration${timestamp}${description}.php`

`Migration`
    : The word migration is prepended to every migration filename

`timestamp`
    : This is the time the migration was created as a Unix epoch timestamp,
      which can be obtained, for example, by executing `date '+%s'`

`description`
    : This is a short, unique suffix roughly describing the migration's purpose

`.php`
    : Each migration is a `php` file, so it gets the `.php` extension
