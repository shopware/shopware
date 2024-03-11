---
title: Add error handling on document preview
issue: NEXT-30160
author: Cedric Engler
author_email: cedric.engler@pickware.de
author_github: Ceddy610
---
# Administration
* Changed the `getDocumentPreview` method on `src/core/service/api/document.api.service.js` so that an error gets caught with the call of the listener
* Changed the `onPreview` method on `src/module/sw-order/component/sw-order-document-card/index.js` so that the property `isLoadingPreview` is changed on `finally`
