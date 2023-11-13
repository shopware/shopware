---
title: Add a missing block to sw-flow-list
issue: NEXT-17997
---
# Administration
* Added missing block `sw_flow_list_grid_actions_custom` in `/src/module/sw-flow/view/listing/sw-flow-list/sw-flow-list.html.twig` to fix breaking change when moving sw-flow-list from page to view.
* Added a block `sw_flow_set_entity_custom_field_modal_custom` in `/src/module/sw-flow/component/modals/sw-flow-set-entity-custom-field-modal/sw-flow-set-entity-custom-field-modal.html.twig` to be able to extend the functionality in module Flow Share of Commercial plugin.
