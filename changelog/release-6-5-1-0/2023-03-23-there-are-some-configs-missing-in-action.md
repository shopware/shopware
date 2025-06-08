---
title: There are some configs missing in action
issue: NEXT-25494
---
# Administration
* Changed `createdComponent` method in `/module/sw-flow/component/modals/sw-flow-grant-download-access-modal/index.js` to always set the value for access field.
* Added `value` watch in `/module/sw-flow/component/modals/sw-flow-grant-download-access-modal/index.js` for watching the value and set empty error.
