---
title: Changed Admin-SDK handler for context.can
issue: https://github.com/shopware/service-enablement/issues/17
author: Sebastian Franze
author_email: s.franze@shopware.com
---
# Administration
* Changed Admin-SDK handler `AdminSDK.Context.getAppInformation`  
  * Added `privileges` to response of `AdminSDK.Context.getAppInformation`
* Changed type of `Extension.permissions` in `src/app/store/extension.store.js` to use `privileges` type from the Admin-SDK