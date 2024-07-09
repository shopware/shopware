---
title: The selected order language is not saved for a manually created orders
issue: NEXT-35343
---
# Administration
* Added new mutation `setLanguageId` into `context.store.ts` to update the current language without storing it in the local storage.
* Added a watcher to the `context.languageId` in the component `sw-order-create-options` to update the language in the `context.api` when the order language changes.
* Changed method `saveFinish` in the component `sw-order-create` to revert the language to the current `context.api.languageId` when the order is saved.
