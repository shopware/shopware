---
title: Change migration system to allow grouping migrations by version
issue: NEXT-12349
---
# Core
* Added `MigrationSource`s `\Shopware\Core\Framework\Migration\MigrationSource.core.V6_3` and `\Shopware\Core\Framework\Migration\MigrationSource.core.V6_4`
* Change directory of core migrations from `src/Core/Migration` into `src/Core/Migration/V6_3`. Replaced them with migrations that extend from those in V6_3 to be backwards compatible.
* Changed `\Shopware\Core\Framework\Migration\MigrationSource.core` to be empty. The directories are now included in `\Shopware\Core\Framework\Migration\MigrationSource.core.V6_3`.
* Changed `\Shopware\Core\Framework\Migration\MigrationSource` to allowing nesting `\Shopware\Core\Framework\Migration\MigrationSource`
* Added method `\Shopware\Core\Framework\Migration\MigrationCollectionLoader::collectAllForVersion`, which will return a collection with all "safe" (matching the mode) `MigrationSource`s including `core`.
* Added parameter `--version-selection-mode` to `database:migrate-destructive`. Possible values are "safe", "blue-green" and "all". Default is "safe".
* Changed `database:migrate` to use `\Shopware\Core\Framework\Migration\MigrationCollectionLoader::collectAllForVersion` with mode "all".
* Changed `database:migrate-destructive` to use `\Shopware\Core\Framework\Migration\MigrationCollectionLoader::collectAllForVersion` with mode defined in parameter `--version-selection-mode`
___
# Storefront
* Moved migrations from src/Storefront/Migration into src/Storefront/Migration/V6_3
___
# Upgrade Information

## Migration system changes

We've changed the migration system to add the following features:

### 1. Defining migrations that are released in the next major

We're switching to a trunk based development in combination with feature flags. New breaking features that are intended 
for the next major are also developed on the trunk. To reduce the chance of unintended breaks in minor and patch releases, 
we should not execute the migration for new major features until its necessary. This also allows us to change the migration if necessary.

### 2. Defining destructive changes that can automatically and safely be executed in accordance with blue-green deployment

Currently, it's impossible to define destructive changes in a sane way.

An Example:
- create migration that adds a new column `newData` which replaces `oldData`
- add a trigger in this migration, which synchronizes the data in the columns, to make the change blue-green compatible
- the old field is deprecated for the next major

Problems:
1. When do we remove the deprecated column/trigger? It's not safe to remove them with the next major, because it prevents a rollback and is not blue-green compatible. 
   The first safe option would be the first update from major to the next minor.
2. It's not possible to define destructive changes in the same migration. Currently, these migrations have to be created manually, after it's safe to execute.

### Migration system upgrade guide

We've grouped the migrations into major versions. By default, all non-destructive migrations are executed up to the current major. 
In contrast, all destructive migrations are executed up to a "safe" point. This can be configured with the mode.

There are three possible values for the mode:
1. `--version-selection-mode=safe`: Execute only "safe" destructive changes. This means only migrations from the penultimate major are executed. 
   So with the update to 6.5.0.0 all destructive changes in 6.3 or lower are executed.
2. `--version-selection-mode=blue-green`: Execute as early as possible, but still blue-green compatible. 
   This means with the update to 6.4.1.0 from 6.4.0.0 all destructive changes in 6.3 or lower are executed.
3. `--version-selection-mode=all`: Execute all destructive changes up to the current major.

To allow this selection, we've moved all migrations from `\Shopware\Core\Framework\Migration\MigrationSource.core` 
into `\Shopware\Core\Framework\Migration\MigrationSource.core.V6_3`. `core` is now empty by default. You can still extend it. 
The execution order is now like this:
1. `core.V6_3`
2. `core.V6_4`
3. newer majors...
4. `core`

This means all new migrations need to be created in the matching major folder. Currently, this is `src/Core/Migration/V6_3`, 
it will be `src/Core/Migration/V6_4` soon. To keep the backwards compatibility, Migrations still need to be defined in `src/Core/Migration`. 
To accomplish that, just create it in the versioned folder and create a class in the old folder that simply extends the other class without changing anything.  

The method `\Shopware\Core\Framework\Migration\MigrationCollectionLoader::collectAllForVersion` will return a collection with all "safe" `MigrationSource`s including `core`.

**bin/console database:migrate --all core**

Should do the sames as before. Alternatively, you can run it for each major:
- `bin/console database:migrate --all V6_3`
- `bin/console database:migrate --all V6_4`
- etc

**bin/console database:migrate-destructive --all core**

This behavior changed! By default, this will only executes "safe" destructive changes. It used to execute all destructive migrations.

To get the old behavior, run:

`bin/console database:migrate-destructive --all --version-selection-mode=all core`

To run the destructive migrations as early as possible but still blue-green, run:

`bin/console database:migrate-destructive --all --version-selection-mode=blue-green core`

**Changes to auto updater**

We've changed the updater to only execute safe destructive migrations. It used to execute **ALL** destructive changes.

## Creating core migrations

To allow implementing this feature with a feature flag, we've to create a legacy migration in `src/Core/Migraiton`, 
which simply extends from the real migration in `src/Core/Migration/$MAJOR`. All migrations have been changed in that way.
The `bin/console database:create-migration` command automatically creates a legacy migration.
