---
title: Deprecate OrderDocumentApiService create method
issue: NEXT-17261
___
# Administration
* Added new method `OrderDocumentApiService::generate`
* Deprecated `OrderDocumentApiService::create` method, use `OrderDocumentApiService::genrate` method instead
* Changed `OrderDocumentApiService` to mark it internal from `6.5.0.0`
* Deprecated method `setOrderDocuments` in `src/module/sw-bulk-edit/state/sw-bulk-edit.state.js` due to unused
