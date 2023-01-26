---
title: Add Custom Entity Page Type and page type registry
issue: NEXT-22656
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: Marcel Brode
---
# Administration
* Added `src/module/sw-cms/service/cms-page-type.service.ts` to enable dynamic registration of new cms page types
* Added `src/module/sw-cms/init/cmsPageTypes.init.ts` to initialize Shopware's default page types for the cms
* Changed `src/app/init/repository.init.js` to add specific cms pages types, when cms-aware flag is given in custom entities
