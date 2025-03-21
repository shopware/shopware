---
title: Fix add/remove order tags
issue: https://github.com/shopware/shopware/issues/7473
author_github: @En0Ma1259
---
# Core
* Added new `sync` method to `Shopware\Core\Framework\DataAbstractionLayer\VersionManager`
* Changed `Shopware\Core\Framework\Api\Sync\SyncService` constructor type `Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface` to `Shopware\Core\Framework\DataAbstractionLayer\VersionManager`. Now calling `VersionManager::sync` in `SyncService::sync` 
___
# Administration
* Changed use sync call for admin order saves
* Changed saving and removing tags not in separat calls, but on saving order
