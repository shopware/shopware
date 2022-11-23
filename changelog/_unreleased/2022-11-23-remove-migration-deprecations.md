---
title: Remove Migration deprecations
issue: NEXT-21203
---
# Core
* Changed all `MigrationSteps` to be `@internal`
* Removed all Migrations in old migration namespaces, all migrations are now in a namespace specifying the major version, where they were added.
* Deprecated `\Shopware\Core\Framework\Migration\MigrationSource::addReplacementPattern()` it will be removed in v6.6.0.0 as it is not used anymore
