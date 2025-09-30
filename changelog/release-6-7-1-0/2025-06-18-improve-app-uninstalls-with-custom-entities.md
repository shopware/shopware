---
title: Fix app uninstalls with custom entities
issue: #9068
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` to explicitly delete custom entities when uninstalling an app, instead of relying on DB level cascade delete constraint. 
  This allows to only update the DB schema if custom entities where removed actually removed, improving the performance for all the uninstalls where no custom entities are affected.
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLifecycle` and `\Shopware\Core\Framework\App\AppStateService` to allow uninstalling apps with custom entities that have restrict delete constraints, when no data is left in the custom entities or the custom entities will be deleted anyway because `keepUserData` is set to `false`.
