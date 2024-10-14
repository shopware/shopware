---
title: Hide update button for extensions managed by composer
issue: NEXT-37869
---
# Core
* Added `managedByComposer` property to `Shopware\Core\Framework\Store\Struct\ExtensionStruct` to indicate if the extension is managed by composer.
* Changed the `isUpdateable` method in frontend component to return false if the extension is managed by composer.
