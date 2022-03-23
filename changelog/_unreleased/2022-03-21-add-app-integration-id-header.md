---
title: Add app integration id header
issue: NEXT-20525
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# API
* Added `\Shopware\Core\PlatformRequest::HEADER_APP_INTEGRATION_ID`
* Added `integrationId` to `bundles` of route `/api/_info/config`
___
# Administration
* Added `sw-app-integration-id` option to `src/Administration/Resources/app/administration/src/core/data/repository.data.ts`
* Added `sw-app-integration-id` to `src/Administration/Resources/app/administration/src/app/state/extensions.store.ts`
* Changed dependency version of `@shopware-ag/admin-extension-sdk` to `^0.0.48`
* Changed `src/Administration/Resources/app/administration/src/core/factory/http.factory.js` to reject errors if `sw-app-integration-id` header is set
