---
title: Add twig block extension for flow builder
issue: NEXT-20255
---
# Administration
* Changed in `src/module/sw-flow/component/sw-flow-sequence-selector/sw-flow-sequence-selector.html.twig`
  * Added block `sw_flow_sequence_extension`.
  * Changed block `sw_flow_sequence_condition` to modify `v-else-if` to `v-if` for element `sw-flow-sequence-condition`.
  * Changed block `sw_flow_sequence_action` to modify `v-else` to `v-if` for element `sw-flow-sequence-action`.
* Added block `sw_flow_sequence_selector_extension_options` in `src/module/sw-flow/component/sw-flow-sequence-selector/sw-flow-sequence-selector.html.twig`
* Added block `sw_flow_sequence_condition_true_arrow_extension_options ` and `sw_flow_sequence_condition_false_arrow_extension_options` in `src/module/sw-flow/component/sw-flow-sequence-condition/sw-flow-sequence-condition.html.twig`
* Added in `src/module/sw-flow/page/sw-flow-list/index.js`.
  * Added data variable `selectedItems` to store grid selection.
  * Added method `selectionChange`.
* Added block `sw_flow_list_grid_bulk_modal_delete_confirm_text` in `src/module/sw-flow/page/sw-flow-list/sw-flow-list.html.twig`.
* Added computed property `isActionSequence` in `src/module/sw-flow/component/sw-flow-sequence/index.js`
