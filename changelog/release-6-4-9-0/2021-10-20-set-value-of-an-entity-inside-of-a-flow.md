---
title: Set value of an entity inside a flow
issue: NEXT-17740
---
# Administration
* Added component `sw-flow-set-entity-custom-field-modal` to show a modal that allows choosing a custom field and setting values for it.
* Added `getCustomFieldDescription` function at `module/sw-flow/component/sw-flow-sequence-action/index.js` to get customer group description.
* Added `SET_CUSTOMER_CUSTOM_FIELD`, `SET_CUSTOMER_GROUP_CUSTOM_FIELD` and `SET_ORDER_CUSTOM_FIELD` into action list at `module/sw-flow/constant/flow.constant.js`.
* Added `customFieldSetCriteria` and `customFieldCriteria` functions at `module/sw-flow/page/sw-flow-detail/index.js` to get customer group data if customer group action is exist.
* Added icon and title for change custom field content action at `module/sw-flow/service/flow-builder.service.js`.
* Added function `showlabel` at `app/component/form/sw-text-editor/index.js`.
* Added function `showlabel` at `app/component/media/sw-media-field/index.js`.
* Added function `unmounted` at `app/component/form/sw-text-editor/sw-text-editor-toolbar/index.js`.
* Deprecated function `beforeDestroy` at `app/component/form/sw-text-editor/sw-text-editor-toolbar/index.js`, use `unmounted` instead.
