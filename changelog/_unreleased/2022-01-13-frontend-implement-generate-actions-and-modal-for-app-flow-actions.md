---
title: Frontend Implement generate actions and modal for app flow actions
issue: NEXT-18949
---
# Administration
* Changed `sw-flow-sequence-action` component in `src/module/sw-flow/component/sw-flow-sequence-action` to add more the app flow action for flow builder.
* Added new component `sw-flow-app-action-modal` in `src/module/sw-flow/component/modals` to show modal that allows setting the fields for app flow actions.
* Added the `emitUpdate` method in `src/app/component/form/sw-form-field-renderer/index.js` to emit more `update` event.
* Changed the `emitChange` method in `src/app/component/form/sw-form-field-renderer/index.js` to emit more `update` event.
* Changed the `getDataForActionDescription` method in `src/module/sw-flow/page/sw-flow-detail/index.js` to render the description for app flow action sequence.
