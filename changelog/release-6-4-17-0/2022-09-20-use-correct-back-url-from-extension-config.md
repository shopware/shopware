---
title: Use correct back url from extension config
issue: NEXT-19196
author: Sebastian Franze
author_email: s.franze@shopware.com
---
# Administration
* Changed `src/app/component/meteor/sw-meteor-navigation/index.js` to `src/app/component/meteor/sw-meteor-navigation/index.ts`
* Added prop `fromLink` to `src/app/component/meteor/sw-meteor-navigation`
* Changed `src/app/component/meteor/sw-meteor-page/index.ts` to `src/app/component/meteor/sw-meteor-page/index.ts`
* Added prop `fromLink` to `src/app/component/meteor/sw-meteor-page`
* Changed `src/module/sw-extension/page/sw-extension-config/index.js` to `src/module/sw-extension/page/sw-extension-config/index.ts`
