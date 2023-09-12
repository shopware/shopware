---
title: Handle exception for VersionManager
issue: NEXT-30181
---
# Core
* Added 2 new exception methods `cannotCreateNewVersion` and `versionMergeAlreadyLocked` in `Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException`
* Added an alternative exception by throwing `DataAbstractionLayerException::cannotCreateNewVersion()` in `cloneEntity` method of `Shopware\Core\Framework\DataAbstractionLayer\VersionManager`
* Added an alternative exception by throwing `DataAbstractionLayerException::versionMergeAlreadyLocked()` in `merge` method of `Shopware\Core\Framework\DataAbstractionLayer\VersionManager`
