---
title: Implement scroll up if section gets deleted
issue: NEXT-17806
---
# Administration
* Added new `sw_flow_sequence_action_actions_transition_group` block in `/module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig` for wrapping action item to handle transition of list.
* Added new `getSequenceId` function in `/module/sw-flow/view/detail/sw-flow-detail-flow/index.js` to get sequence id as key to render list.
* Added new `sw_flow_detail_flow_transition_group` block in `/module/sw-flow/view/detail/sw-flow-detail-flow/sw-flow-detail-flow.html.twig` for wrapping action item to handle transition of list.
