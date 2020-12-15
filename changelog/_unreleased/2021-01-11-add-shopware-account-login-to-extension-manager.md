---
title: Add shopware account login to extension manager
issue: NEXT-12614
flag: FEATURE_NEXT_12608
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Changed the method `checkIfRouteMatchesLink` in `sw-tabs-item` to fix endless render loop which freezes the browser
* Added the component `sw-extension-my-extensions-account`
* Added the component `sw-extension-my-extensions-extension-config`
* Added the component `sw-extension-my-extensions-index`
* Added the component `sw-extension-my-extensions-listing`
* Added the service `extension-error-handler.service.js` which is a modified duplicate of `plugin-error-handler.service.js`
* Added login actions to `extensions.store`. These are also modified duplicates of the `plugin.s`tore
