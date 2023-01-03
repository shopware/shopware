---
title: Add a missing block to sw-flow-list
issue: NEXT-17997
---
# Administration
* Added missing blocks `sw_flow_list_grid_actions_custom` and `sw_flow_list_modal_content_custom` in `/src/module/sw-flow/view/listing/sw-flow-list/sw-flow-list.html.twig` to fix breaking change when moving sw-flow-list from page to view.
* Added missing block `sw_flow_index_modal_content_custom` in `src/module/sw-flow/page/sw-flow-index/sw-flow-index.html.twig` to fix breaking change when splitting sw-flow-index from sw-flow-list.
* Added a block `sw_flow_set_entity_custom_field_modal_custom` in `/src/module/sw-flow/component/modals/sw-flow-set-entity-custom-field-modal/sw-flow-set-entity-custom-field-modal.html.twig` to be able to extend the functionality in module Flow Share of Commercial plugin.
