---
title: Confirm before leaving bulk edit
issue: NEXT-18072
flag: FEATURE_NEXT_17261
---
# Administration
* Added the following instance lifecycles in `sw-bulk-edit-save-modal` component:
    * `created`
    * `beforeDestroy`
* Added the following methods in `sw-bulk-edit-save-modal` component:
    * `createdComponent`
    * `beforeDestroyComponent`
    * `addEventListeners`
    * `removeEventListeners`
    * `beforeUnloadListener`
