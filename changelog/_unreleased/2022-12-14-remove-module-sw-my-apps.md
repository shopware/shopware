---
title: Remove module sw-my-apps
issue: NEXT-14065
author: Sebastian Franze
author_email: s.franze@shopware.com
---
# Administration
* Removed module `sw-my-apps`
  * Removed component `src/module/sw-my-apps/component/sw-my-apps-error-page`
  * Removed component `src/module/sw-my-apps/component/sw-my-apps-page`
  * Removed route `/sw/my/apps/{appName}/{moduleName}`
* Added component `src/module/sw-extension/component/sw-extension-app-module-error-page`
* Added component `src/module/sw-extension/page/sw-extension-app-module-page`
* Added route `sw/extension/module/{appName}/{appModule}`
