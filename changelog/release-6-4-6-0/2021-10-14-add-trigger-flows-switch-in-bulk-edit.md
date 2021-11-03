---
title: Add trigger flows switch in bulk edit
issue: NEXT-17908
---
# Core
* Added a new public constant `HEADER_SKIP_TRIGGER_FLOW = 'sw-skip-trigger-flow'` in `Shopware\Core\PlatformRequest`.
* Added new state `SKIP_TRIGGER_FLOW` in `Shopware\Core\Framework\Context`.
* Changed `Shopware\Core\Framework\Routing\ApiRequestContextResolver` to add `SKIP_TRIGGER_FLOW` state to the context from the request headers.
___
# Administration
* Added `bulkEditData` prop in `sw-bulk-edit-save-modal` and `sw-bulk-edit-save-modal-confirm` components.
* Added the following computed properties in `sw-bulk-edit-save-modal-confirm` component:
    * `isFlowTriggered`
    * `triggeredFlows`
* Added `sw_bulk_edit_save_modal_confirm_trigger_flows` block in `sw-bulk-edit-save-modal-confirm` component template.
* Added the following lifecycles in `sw-bulk-edit-order` component:
    * `beforeCreate`
    * `beforeDestroy`
* Changed `bulkEditStatus` method in `src/module/sw-bulk-edit/service/handler/bulk-edit-order.handler.js`.
* Added `state` in `sw-bulk-edit` module.
