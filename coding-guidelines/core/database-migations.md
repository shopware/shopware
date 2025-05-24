## Introduction
Changing the database structure of Shopware is an important and sensitive topic, because it can effect the installation of customers and their data in many ways.
Therefore, it is important for every developer to understand the core principles of database migrations, also in the case of backward compatibility.

Migrations in Shopware are grouped by major versions.
This allows for a sane execution of destructive migrations on customer systems.
Database changes in minor or patch releases should always be non-destructive.
See [backward compatibility](#backward-compatibility) for more information.

## Create a migration

Use `bin/console database:create-migration` to create a new migration in the current major namespace.

Make sure to always test your migration against the defined rule set -> [Important Rules](#important-rules)

## The migration class

Migrations are created in their own major version namespace.
As an example, migrations which should not run before the `v6.5.0.0` are located in the `Core\Migration\V6_5` namespace.

The migration consists of two separated steps: `update` and `updateDestructive`.

<table>
    <tr>
        <td><code>update</code></td>
        <td>Contains backward compatible changes needed for your new feature.</td>
    </tr>
    <tr>
        <td><code>updateDestructive</code></td>
        <td>Contains non-reversible changes to the database. For example deleting a database table, dropping table columns, etc.</td>
    </tr>
</table>

## Backward compatibility

As every other change, also your database changes should always be [backward compatible](https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html) for minor and patch releases and support blue-green deployment.
A common technique is the [expand and contract](https://www.tim-wellhausen.de/papers/ExpandAndContract/ExpandAndContract.html) pattern, which will help you to implement your changes in a backward compatible way.

* **Expand**: Instead of renaming an existing column, create a new column with the updated name. (non-destructive)
* **Migrate**: Move the data from the old column to the new column.
* **Contract**: Once you verify that your code is functioning correctly with the new column, then delete the old column and make it non-existent.
  This must only be done in the `updateDestructive` method.

### Mode for executing destructive changes

There are different `version-selection-modes` for customers to choose from when executing migrations.

<table>
    <tr>
        <td width=160><code>mode=all</code></td>
        <td>Executes destructive migrations up to and including the current major version</td>
    </tr>
    <tr>
        <td><code>mode=blue-green</code></td>
        <td>Executes destructive migrations up to and including the previous major version</td>
    </tr>
    <tr>
        <td><code>mode=safe</code></td>
        <td>Executes destructive migrations up to and including two majors before the current major version</td>
    </tr>
</table>

> **NOTE:** The default mode is `mode=safe`.

## Migration execution order

Migrations are executed in following order.

1. migrations from v6_3 namespace
2. migrations from v6_4 namespace
3. migrations from v6_5 namespace  
   ...
4. core 'legacy' migrations

> **HINT:** You can run migrations specific to a major version with `bin/console database:migrate --all core.V6_7` where `core.V6_7` represents the major version you want to execute.

---

## Important Rules

**To ensure the stability of updates and the software itself, it is imperative that the following rules are always followed when creating new migrations.**

---

### 1. NEVER change an executed migration

You cannot alter an executed, or already released, migration.
If the migration was not yet part of a public release, you can still change it.
For example, the current major is 6.6, so you can still change migrations in the 6.7 migrations folder.
If the migration was executed already, you need to write a new migration to do the changes.
Otherwise, an existing system will not have the same structure after an update as a new installation.
The only exception is when a migration was incorrectly created and causes errors.

### 2. Migrations must be able to be executed more than once

If a migration fails, make sure that it can be executed again.
A failure can happen for various reasons, such as a timeout, a connection error, or a syntax error.
A migration should check whether structures have already been created to avoid creating duplicates.

You can easily achieve this by adding the `IF [NOT] EXISTS` condition to commands like `CREATE TABLE` or `DROP TABLE`.
There are also helper methods available to check for the existence of a table or column. E.g.:
- `\Shopware\Core\Framework\Migration\MigrationStep::dropTableIfExists`
- `\Shopware\Core\Framework\Migration\MigrationStep::dropColumnIfExists`
- `\Shopware\Core\Framework\Migration\AddColumnTrait::columnExists`

> **NOTE:** Commands like `ALTER TABLE` however do not have a conditional `IF EXISTS` check. You **must** query the table for its columns manually.

### 3. Do not trust any identifier

Identifiers on a customer system can always be different from those on a development environment.
A database query for the identifier should be initiated in advance.

### 4. Do not trust data of customer environments

The data of production environments sometimes produce very confusing data constructs.
Therefore, never rely on the existence of data or structures.
Always program migrations very defensively with exact queries on the situation.

### 5. Don't hurt customized data

There is data that is often individualized by customers.
Under no circumstances may a migration overwrite individualized customer data.
Always check this in your migration.

This can easily be done by checking, whether the `updated_at` field is `null`:

```mysql
UPDATE `product` SET name = 'foobar' WHERE updated_at IS NULL;
```

### 6. Performance / Duration

A migration must never take longer than 10 seconds on your local system.
We do not know the timeout values of the customers, so this value should never be exceeded.
Customer systems may be slower than developer systems, and contain a lot of data.
Make sure to test your migration with big data sets.

### 7. There are no default languages

The customers can select any language as their default.
Don't rely on any language as given, neither English nor German.

Use the `ImportTranslationsTrait` to your advantage:

```php
// src/Core/Migration/V6_3/Migration1595422169AddProductSorting.php

...
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
...

public function createDefaultSortingsWithTranslations(Connection $connection): void
{
    // hard-coded default data coming with the release of product-sortings
    foreach ($this->getDefaultSortings() as $sorting) {
        $connection->insert(ProductSortingDefinition::ENTITY_NAME, $sorting);

        $translations = new Translations(
            ['product_sorting_id' => $sorting['id'], 'label' => $sorting['translations']['de-DE']],
            ['product_sorting_id' => $sorting['id'], 'label' => $sorting['translations']['en-GB']]
        );

        $this->importTranslation('product_sorting_translation', $translations, $connection);
    }
}
```

### 8. Migration Tests

For each migration you write you need to write a test, that verifies that the migration works as expected and adheres to the guidelines stated above.
Place your migration test inside the `tests/Migration/V6_*` folder.
To make those tests fast to run and easier to understand you should not use any of the "legacy test behaviours" like `IntegrationTestBehaviour` or `KernelTestBehaviour`.
You should also especially not rely on the kernel being booted and the service container being available.
To test your migration you can get a database connection via `KernelLifecycleManager::getConnection()`.
Besides obviously relying on the database, the migration tests should behave like unit tests and rely on nothing else external.

**Be careful with implicit commits**

If data is updated in a migration, a test must be written for this migration.
You can use database transactions in your migration tests to keep the database tidy after each test.
For that use the `MigrationTestTrait` trait, which encapsulates your migration test in a database transaction.

Unfortunately, database transactions won't work with DDL commands.
DDL commands will fire an implicit commit and end an active transaction.
> https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html

All DDL commands **must** be done outside of transactions.
Therefore, you **must** undo your DDL commands manually after the test and it is best to handle the transaction start and rollback manually in your test and not rely on the `MigrationTestTrait`.

**Obsolete migration tests**

Migrations run in a specified order, therefore they may rely on a specific state (columns present, triggers active etc.) in the DB.
However, you can not expect a specific state inside your migration tests, as all migrations are already executed when the migration tests are run.
The destructive part of the migration you are testing may already be run or not, also a new migration may have altered the current state of the DB in the meantime etc.
If your test relies on a specific state, you should create that state explicitly in your test.
Do to that you could do:
1. Rename the table your migration relies on to temporary name
2. Recreate the table manually with the state (columns present, triggers active etc.) your test needs
3. Run your migration test
4. Drop your newly created table and rename the temporary table back to the original name


### 9. Database Table Naming
When naming database tables, it's essential to steer clear of prefixes like 'swag_' that were historically used for plugins.
This practice is crucial for maintaining database consistency.
Additionally, ensure you assign suitable names to the tables that accurately represent their content and purpose.

Dos:
For instance, when creating a database table that stores customer information, a suitable name could be 'customer_data' or just 'customer'.
This follows the practice of using descriptive, singular, snake case names that align with the table's purpose.

Don'ts:
On the other hand, avoid using prefixes such as 'swag_' as seen in past plugins.
For example, refraining from naming a table 'swag_order_history' ensures better scalability, clarity, and uniformity within the database schema.