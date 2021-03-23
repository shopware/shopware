---
title: Remove the plugin manager from the administration
issue: NEXT-13821
author: Timo Altholtmann
---
# Administration
*  Removed the plugin manager from the administration since it is replaced by the `sw-extension` module
* Removed module `sw-plugin`
* Removed component `sw-plugin-config`
* Removed component `sw-plugin-description`
* Removed component `sw-plugin-file-upload`
* Removed component `sw-plugin-last-updates-grid`
* Removed component `sw-plugin-store-login`
* Removed component `sw-plugin-store-login-status`
* Removed component `sw-plugin-table-entry`
* Removed component `sw-plugin-updated-grid`
* Removed component `sw-plugin-manager`
* Removed component `sw-plugin-license-list`
* Removed component `sw-plugin-list`
* Removed component `sw-plugin-recommendation`
* Removed component `sw-plugin-updates`
* Removed service `plugin-error-handler`
* Removed vuex store `swPlugin`
___
# Upgrade Information
## Removed plugin manager
The plugin manager in the administration is removed with all of its components and replaced by the `sw-extension` module.
