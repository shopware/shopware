---
title: Add block supports for Flow Export on commercial plugin
issue: NEXT-22691
---
# Administration
* Added blocks `sw_flow_list_smart_bar_actions_extension`, `sw_flow_list_grid_actions_custom`, `sw_flow_list_modal_content_custom` in `module/sw-flow/page/sw-flow-list/sw-flow-list.html.twig` to be able to extend the functionality in module Flow Export of Commercial plugin.
* Added variable `isDeleting` in `module/sw-flow/page/sw-flow-list/index.js` for showing delete modal only condition.
* Changed method `onDeleteFlow` in `module/sw-flow/page/sw-flow-list/index.js` to set `isDeleting` to `true`.
* Changed method `onConfirmDelete` in `module/sw-flow/page/sw-flow-list/index.js` to set `isDeleting` to `false`.
* Changed block `sw_flow_list_grid_action_modal` in `module/sw-flow/page/sw-flow-list/sw-flow-list.html.twig` to show the delete modal only if `isDeleting` is true.
* Added block `sw_flow_sequence_action_item_custom` in `module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig` to show the sequence action error in the module Flow Export of Commercial plugin.
* Added block `sw_flow_sequence_condition_content_custom` in `/module/sw-flow/component/sw-flow-sequence-condition/sw-flow-sequence-condition.html.twig` to show the sequence condition error in the module Flow Export of Commercial plugin.
* Added block `sw_flow_change_customer_group_modal_content_custom` in `module/sw-flow/component/modals/sw-flow-change-customer-group-modal/sw-flow-change-customer-group-modal.html.twig` to show the change customer group modal error in the module Flow Export of Commercial plugin.
* Added block `sw_flow_mail_send_modal_custom` in `module/sw-flow/component/modals/sw-flow-mail-send-modal/sw-flow-mail-send-modal.html.twig` to show the change mail send modal error in the module Flow Export of Commercial plugin.
* Added block `sw_flow_tag_modal_content_custom` in `module/sw-flow/component/modals/sw-flow-tag-modal/sw-flow-tag-modal.html.twig` to show the change customer group modal error in the module Flow Export of Commercial plugin.
