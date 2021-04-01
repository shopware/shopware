---
title: Remove plugin manager dependencies
issue: NEXT-14498
author: Timo Altholtmann
 
---
# Core
* Removed class `PluginController`
* Removed controller for route `/api/_action/plugin/upload`
* Removed controller for route `/api/_action/plugin/delete`
* Removed controller for route `/api/_action/plugin/refresh`
* Removed controller for route `/api/_action/plugin/install`
* Removed controller for route `/api/_action/plugin/uninstall`
* Removed controller for route `/api/_action/plugin/activate`
* Removed controller for route `/api/_action/plugin/deactivate`
* Removed controller for route `/api/_action/plugin/update`
___
# Administration
* Removed service `pluginService`
* Removed service `extensionService`
___
# Upgrade Information
## Removed plugin manager code
The controller for the plugin manager with all of its routes is removed and replaced by the `ExtensionStoreActionsController`.
