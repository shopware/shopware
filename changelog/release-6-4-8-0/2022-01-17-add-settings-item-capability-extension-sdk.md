---
title: Add settings item capability extension sdk
issue: NEXT-18121
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `src/Administration/Resources/app/administration/src/app/state/extension-sdk-module.store.ts` as state `extensionSdkModules`
* Added `src/Administration/Resources/app/administration/src/app/init/settings-item.init.ts` to initialize extension sdk settings items
* Changed `src/Administration/Resources/app/administration/src/module/sw-extension-sdk/page/sw-extension-sdk-module/index.js` to use state `extensionSdkModules`
* Changed dependency version of `@shopware-ag/admin-extension-sdk` from `0.0.34` to `0.0.35`
