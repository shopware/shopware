---
title: Survive MySQL 8.4 non-standard FK guard failures in migrations
issue: 16240
author: Thuy Le
author_email: t.le@shopware.com
author_github: @vienthuong
---
# Core
* Added `Shopware\Core\Framework\Migration\NonStandardFkGuard`, which executes migration DDL and retries once with `restrict_fk_on_non_standard_key` relaxed when MySQL 8.4 rejects the statement through MySQL bug [#118151](https://bugs.mysql.com/bug.php?id=118151). On MariaDB and MySQL < 8.4 the variable does not exist and there is no retry.
* Added `Shopware\Core\Framework\Migration\MigrationStep::executeDdlStatement()` so extension migrations can route raw DDL through the guard. The method is `@internal` only because it will be removed once MySQL fixes the bug; it is safe to call in the meantime.
* Changed `MigrationStep::dropColumnIfExists()`, `dropForeignKeyIfExists()`, `dropIndexIfExists()`, `AddColumnTrait::addColumn()` and `InheritanceUpdaterTrait::updateInheritance()` to execute their DDL through the guard.
* Changed the product-table migrations `Migration1707807389ChangeAvailableDefault` and `Migration1714659357CanonicalProductVersion` to execute their DDL through the guard, so upgrades no longer fail on MySQL 8.4 shops that carry a foreign key against a non-unique `product` column.
* Added the PHPStan rule `NonStandardFkGuardRule`, which flags raw `ALTER TABLE` / `CREATE INDEX` / `DROP INDEX` statements executed directly on the connection inside core migrations.
