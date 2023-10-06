---
title: Flow api to dispatch custom app event.
issue: NEXT-21817
---
# Core
* Added new class `Shopware\Core\Content\Flow\Dispatching\Storer\CustomAppStorer.php` to store the representation of available data and restore the available data for `StorableFlow` from the custom app event.
* Added new exception class `Shopware\Core\Content\Flow\Exception\CustomTriggerByNameNotFoundException` used to throw when the trigger name is invalid or uninstalled!
___
# API
*  Added new route `/api/_action/trigger-event/{eventName}` to dispatch a custom app event.
