---
title: Inform administrators about assigned themes when deactivating plugins
issue: NEXT-8690
---
# Administration
* Added snippet `messageDeactivationFailedThemeAssignment` to
  * `src/module/sw-extension/snippet/de-DE.json`
  * `src/module/sw-extension/snippet/en-GB.json`
* Changed `src/module/sw-settings-shopware-updates/page/sw-settings-shopware-updates-wizard/index.js` to show a more useful error message, when assigned themes need to be deactivated during an update
