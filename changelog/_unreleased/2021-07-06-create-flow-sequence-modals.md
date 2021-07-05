---
title: Create flow sequence modals
issue: NEXT-15142
flag: FEATURE_NEXT_8225
---
# Administration
* Added flow service in `src/module/sw-flow/service/flow.service.js`.
* Changed in `src/module/sw-flow/component/sw-flow-sequence-modal/index.js`.
    * Added method `processSuccess` to handle event after processed success.
    * Added method `onClose` to close the modal.
* Changed in `src/module/sw-flow/component/sw-flow-sequence-modal/sw-flow-sequence-modal.html.twig`.
    * Added dynamic component to render the component which provider.
* Changed in `src/module/sw-flow/component/sw-flow-sequence-condition/index.js`.
    * Removed data `showRuleModal`.
    * Added data `actionModal`.
    * Added method `onCreateNewRule` to toggle create new rule modal.
    * Added method `onCloseModal` to close create new rule modal.
    * Added method `onCreateRuleSuccess` to handle action after create new rule success.
* Changed in `src/module/sw-flow/component/sw-flow-sequence-condition/sw-flow-sequence-condition.html.twig`.
    * Add block `sw_flow_sequence_condition_create_rule_modal` to show create new rule modal.
* Added a new `sw-flow-create-rule-modal` component.
