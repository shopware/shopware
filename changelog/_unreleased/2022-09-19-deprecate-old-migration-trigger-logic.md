---
title: Deprecate old migration trigger logic
issue: NEXT-23226
---
# Core
* Deprecated the old database migration trigger logic
  * Deprecated method `\Shopware\Core\Framework\Migration\MigrationStep::addForwardTrigger()`, use `createTrigger` instead.
  * Deprecated method `\Shopware\Core\Framework\Migration\MigrationStep::addBackwardTrigger()`, use `createTrigger` instead.
  * Deprecated method `\Shopware\Core\Framework\Migration\MigrationStep::addTrigger()`, use `createTrigger` instead.
  * Deprecated const `\Shopware\Core\Framework\Migration\MigrationStep::MIGRATION_VARIABLE_FORMAT`.
___
# Next Major Version Changes
## Remove old database migration trigger logic
The `addForwardTrigger()`, `addBackwardTrigger()` and `addTrigger()` methods of the `MigrationStep`-class were removed, use `createTrigger()` instead.
Do not rely on the state of the already executed migrations in your database trigger anymore.
Additionally, the `@MIGRATION_{migration}_IS_ACTIVE` DB connection variables are not set at kernel boot anymore.
