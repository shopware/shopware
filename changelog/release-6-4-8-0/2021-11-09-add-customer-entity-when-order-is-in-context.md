---
title: Add Customer entity when order is in context on Flow builder
issue: NEXT-17902
---
# Core
* Added implementation `CustomerAware` in these events: 
  * `OrderStateMachineStateChangeEvent`
  * `CheckoutOrderPlacedEvent`
  * `CustomerChangedPaymentMethodEvent`
* Added `getCustomerId` function into these events:
  * `OrderStateMachineStateChangeEvent`
  * `CheckoutOrderPlacedEvent`
  * `CustomerChangedPaymentMethodEvent`
* Added new exception `src/Core/Content/Flow/Exception/CustomerDeletedException.php`
* Changed `execute` function in `src/Core/Content/Flow/Dispatching/FlowExecutor.php` to add exception message and code when throw `ExecuteSequenceException`.
___
# Administration
* Added `mapActionType` function into `src/module/sw-flow/service/flow-builder.service.js` to map correct action name.
* Added `getAvailableEntities` function into `src/module/sw-flow/service/flow-builder.service.js` to get available entities for modal action.
* Changed `getActionTitle` function in `src/module/sw-flow/service/flow-builder.service.js` to get correct action name by `mapActionType` function.
* Changed `createdComponent` method in `src/module/sw-flow/component/modals/sw-flow-tag-modal/index.js` to get correct entity when created component.
* Changed `onSaveActionSuccess` method in `src/module/sw-flow/component/sw-flow-sequence-action/index.js` to get correct action name by `mapActionType` function before saving.
* Changed `getEntityOptions` method in `src/module/sw-flow/component/modals/sw-flow-tag-modal/index.js` and `src/module/sw-flow/component/modals/sw-flow-set-entity-custom-field-modal/index.js` to get entity options by `flowBuilderService`.
* Changed `entityOptions` computed in `src/module/sw-flow/component/modals/sw-flow-affiliate-and-campaign-code-modal/index.js` to get entity options by `flowBuilderService`.
* Added prop `action` to:
  * `src/module/sw-flow/component/sw-flow-sequence-modal/index.js`
  * `src/module/sw-flow/component/modals/sw-flow-tag-modal/index.js`
  * `src/module/sw-flow/component/modals/sw-flow-set-entity-custom-field-modal/index.js`
  * `src/module/sw-flow/component/modals/sw-flow-affiliate-and-campaign-code-modal/index.js`
