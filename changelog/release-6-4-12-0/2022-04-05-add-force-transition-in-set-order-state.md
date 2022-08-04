---
title: Add force transition in set order state
issue: NEXT-18753
---
# Core
* Changed `SetOrderStateAction` class in `Shopware\Core\Content\Flow\Dispatching\Action` to support force transition when change order state.
* Changed `getTransitionDestinationById` function in `Shopware\Core\System\StateMachine\StateMachineRegistry` to get `toPlace` if context have force transition state.
___
# Administration
* Added `sw_flow_set_order_state_modal_force_transition` block in `src/Administration/Resources/app/administration/src/module/sw-flow/component/modals/sw-flow-set-order-state-modal/sw-flow-set-order-state-modal.html.twig` to allow select force transition.
* Added variable `config.is_force_transition` in `src/Administration/Resources/app/administration/src/module/sw-flow/component/modals/sw-flow-set-order-state-modal/index.js` to allow force transition.
* Changed `getSetOrderStateDescription` function in `src/Administration/Resources/app/administration/src/module/sw-flow/component/sw-flow-sequence-action/index.js` to show the force transition description.
