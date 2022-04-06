---
title: Add permissions to extensions
issue: NEXT-18126
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# API
* Added app permissions to `/api/_info/config`
___
# Administration
* Added permissions to the `context.store.ts`
* Added permissions to the `extension.store.ts`
* Added `ExtensionAPI` property to `shopware.ts`
* Changed `extension.store.ts` ExtensionState type hint to `@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver::extensions`
* Changed `global.types.ts` ExtensionState type hint to `@shopware-ag/admin-extension-sdk/es/privileges/privilege-resolver::extensions`
* Changed `@shopware-ag/admin-extension-sdk/es/channel::handle` usage to `Shopware.ExtensionApi.handle()`
* Changed `sw-iframe-renderer` to append app privileges to the iFrame src
