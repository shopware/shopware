---
title: Allow changes of action order in sequence
issue: NEXT-17968
---
# Administration
* Added `actionsWithoutStopFlow` method in `/src/module/sw-flow/component/sw-flow-sequence-action/index.js` for getting the whole actions without stop flow action in same group.
* Added `showMoveOption` method in `/src/module/sw-flow/component/sw-flow-sequence-action/index.js` to show or hide move behavior.
* Added `moveAction` method in `/src/module/sw-flow/component/sw-flow-sequence-action/index.js` for handling move action behavior. 
* Added `sw_flow_sequence_action_actions_item_button_move_up` block in `/src/module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig` for adding move up button.
* Added `sw_flow_sequence_action_actions_item_button_move_down` block in `/src/module/sw-flow/component/sw-flow-sequence-action/sw-flow-sequence-action.html.twig` for adding move down button.
