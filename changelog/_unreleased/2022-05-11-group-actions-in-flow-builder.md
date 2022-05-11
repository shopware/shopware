---
title: Group actions in flow builder
issue: NEXT-21284
---
# Administration
* Added new `groups` method in `src/module/sw-flow/component/sw-flow-sequence-action/index.js` to group flow builder actions.
* Added new `ACTION_GROUP`, `GROUPS`, `GENERAL_GROUP`, `TAG_GROUP`, `CUSTOMER_GROUP`, `ORDER_GROUP` constants in `src/module/sw-flow/constant/flow.constant.js` to define the groups for actions.
* Added new `actionGroups` function in `src/module/sw-flow/state/flow.state.js`,
* Added new `sw_flow_sequence_action_list` block in `src/module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig`.
* Changed listing flow builder actions from `sw-single-select` to `sw-grouped-single-select` component in `src/module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig`.
* Changed `ruleCriteria` method in `src/module/sw-flow/component/sw-flow-sequence-condition/index.js` to sorting rules list.
