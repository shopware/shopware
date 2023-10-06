---
title: Re-styling for disabled actions in Flow Builder
issue: NEXT-21562
---
# Administration
* Changed the `appBadge` function in `src/module/sw-flow/component/modals/sw-flow-app-action-modal/index.js` to show the app name on the app action modal title.
* Added `.sw-field--checkbox__content label` class in `src/module/sw-flow/component/modals/sw-flow-app-action-modal/sw-flow-app-action-modal.scss`.
* Changed the `sw_flow_sequence_action_actions_item` and `sw_flow_sequence_action_actions_item_context_button` blocks to show the tooltip when action disabled.
* Changed the `&__disabled` class in `src/module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.scss`.
