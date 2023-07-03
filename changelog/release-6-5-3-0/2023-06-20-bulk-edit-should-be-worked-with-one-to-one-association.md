---
title: Bulk Edit should be worked with One To One Association.
issue: NEXT-28600
---
# Administration
* Changed method `buildBulkSyncPayload` of service `src/module/sw-bulk-edit/service/handler/bulk-edit-base.handler.js` to handle the case One-to-one association field
* Added method `isOneToOneAssociation` to `entity-definition` 
