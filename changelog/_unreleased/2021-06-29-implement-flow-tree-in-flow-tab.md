---
title: Implement flow tree in flow tab
issue: NEXT-15577
flag: FEATURE_NEXT_8225
---
# Administration
* Changed in `src/module/sw-flow/acl/index.js` to update viewer and editor flow privileges
* Changed in `src/module/sw-flow/page/sw-flow-detail/index.js`.
    * Added computed `flowCriteria`
    * Changed method `onSave` to handle saving logic.
    * Added method `removeAllSelectors` to remove all selector sequences before saving.
    * Added method `validateEmptySequence`to validate if sequence is empty before saving.
* Changed in `src/module/sw-flow/view/detail/sw-flow-detail-flow/index.js`.
    * Added computed `flowSequencesRepository`
    * Added computed `formatSequences`
    * Added computed `rootSequences` to get the sequences which does not have parentId
    * Added watcher `flow`
    * Added method `convertSequenceData` to convert flowSequence data to tree data.
    * Added method `convertToTreeData` to convert flowSequences to tree data after it is grouped by displayGroup.
    * Added method `createSequence` to create new flow sequence entity.
    * Added method `onEventChange` to set event name for the current flow.
    * Added method `onAddRootSequence` to add a new root sequence.
* Added flow state in `src/module/sw-flow/state/flow.state.js`.
* Changed in `src/module/sw-flow/component/sw-flow-sequence/index.js`
    * Added props `disabled`
    * Added computed property `sequenceData`
    * Added computed property `isSelectorSequence`
    * Added computed property `isConditionSequence`
* Changed in `src/module/sw-flow/component/sw-flow-sequence/sw-flow-sequence.html.twig`
    * Added block `sw_flow_sequence_true_block` to cover true block of a condition sequence.
    * Added block `sw_flow_sequence_false_block` to cover false block of a condition sequence.
* Changed in `src/module/sw-flow/component/sw-flow-sequence-action/index.js`
    * Added props `disabled`
    * Added computed `flowSequencesRepository`
    * Added computed `sequenceData` to get correct action data from props `sequence`
    * Added computed `showAddAction` to show add action or not.
    * Added computed `actionClasses` to get style for action container.
    * Added watcher for `sequence` props
    * Added method `addAction` to handle add action logic.
    * Added method `removeAction` to handle remove action logic.
    * Added method `removeActionContainer` to handle remove action logic.
    * Added method `getActionInfo` to get action info from action options.
    * Added method `toggleAddButton` to toggle showing add button of action selection.
    * Added method `sortByPosition` to sort sequenceData based on position of its items.
    * Added method `stopFlowStyle` to get stop flow for action item.
    * Added method `setFieldError` to set error if sequence has empty action.
    * Added method `removeFieldError` to remove error if sequence has an action.
* Changed in `src/module/sw-flow/component/sw-flow-sequence/sw-flow-sequence-action.html.twig`
    * Added block `sw_flow_sequence_action_actions_list` to show action list.
    * Added block `sw_flow_sequence_action_add_action` to cover add action container.
* Changed in `src/module/sw-flow/component/sw-flow-sequence-condition/index.js`
    * Added props `disabled`
    * Added computed `flowSequencesRepository`
    * Added computed `ruleRepository`
    * Added watcher for `sequence` props
    * Added method `getRuleDetail` to get rule detail.
    * Added method `addIfCondtion` to add a condition.
    * Added method `addThenAction` to add an action.
    * Added method `showArrowIcon` to check whether show arrow icon or context button.
    * Added method `disabledAddSequence` to disable context button. 
    * Added method `removeCondition` to remove condition.
    * Added method `arrowClasses` to get style for arrows.
    * Added method `createSequence` to create new flow sequence entity.
    * Added method `setFieldError` to show error if rule is empty.
    * Added method `removeFieldError` to remove error if rule is assigned.
    * Added method `toggleAddButton` to toggle showing add button of rule selection.
* Changed in `src/module/sw-flow/component/sw-flow-sequence/sw-flow-sequence-condition.html.twig`
    * Added block `sw_flow_sequence_condition_true_arrow` to show true arrow on the right of the sequence.
    * Added block `sw_flow_sequence_condition_false_arrow` to show true arrow on the bottom of the sequence.
    * Added block `sw_flow_sequence_condition_add_rule` to cover add rule container.
    * Added block `sw_flow_sequence_condition_add_button` to show add button.
* Changed in `src/module/sw-flow/component/sw-flow-sequence-selector/index.js`
    * Added computed `title` to get title based on sequence's parentId and position.
    * Added computed `helpText` to get title based on sequence's parentId, position and true case.
* Changed in `src/module/sw-flow/component/sw-flow-trigger/index.js`.
    * Changed method `closeOnClickOutside` to close the event list only when user click on the content of the lowest children item.
    * Added watcher `searchTerm`
    * Added method `getBreadcrumb` to generate search item's name correctly.
    * Added method `onClickSearchItem` to emit event `option-select` to change event name.
* Changed in `src/module/sw-flow/component/sw-flow-trigger/sw-flow-trigger.html.twig`  
    * Changed block `sw_flow_trigger_tree_transition` to set transition when event tree open.
    * Added block `sw_flow_trigger_search_list` to show search result list.
    * Added block `sw_flow_trigger_search_empty` to show no event found text.
* Added `src/module/sw-flow/constant/flow.constant.js` to store constant.
