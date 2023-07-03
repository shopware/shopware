---
title: Handle for unknown trigger at flow builder.
issue: NEXT-24807
---
# Core
* Added parameter `$flowEventPersister` to method `Shopware\Core\Framework\App\AppStateService::__construct`.
___
# Administration
* Changed in component `sw-flow-sequence-action`.
  * Added boolean props `isUnknownTrigger` default is false.
  * Added map getter `hasAvailableAction`. 
* Changed in component `sw-flow-trigger`.
  * Deprecated `event` from data use `triggerEvents` instead.
  * Deprecated method `getBusinessEvents` use state dispatch action `fetchTriggerActions` instead to get trigger event.
  * Added boolean props `isUnknownTrigger` default is false.
  * Added method `triggerNamePlaceholder` return `sw-flow.detail.trigger.unknownTriggerPlaceholder` when the trigger is not available otherwise return `sw-flow.detail.trigger.placeholder`.
  * Modified method `getEventName` return empty if the current trigger is unknown.
* Changed in component `sw-flow-detail`.
  * Added computed `flowCustomEventRepository`.
  * Added computed `isUnknownTrigger` return `true` if the current trigger is available otherwise return `false`.
  * Added getter state `triggerEvents`.
  * Modified method `getDetailFlow`, add state dispatch `fetchTriggerActions` to get trigger events from `businessEventService::getBusinessEvents()` before get the flow data.
* Changed in state `flow.state.js`.
  * Added setter method `setTriggerEvents`
  * Added getter method `triggerEvents`
  * Added getter method `hasAvailableAction` return `true` if the current action is available otherwise return `false`
  * Added action `fetchTriggerActions`
  * Added `originAvailableActions` default is empty to `state`
* Changed in state `sw-flow-detail-flow`.
  * Added boolean props `isUnknownTrigger` default is false.
  * Added map getter `availableActions`.
  * Added map getter `hasAvailableAction`.
* Changed in state `sw-flow-list`.
  * Added map getter `triggerEvents`.
  * Modified method `getList`, add state dispatch `fetchTriggerActions` to get trigger events from `businessEventService::getBusinessEvents()` before get the flow data..
  * Added method `isValidTrigger` return `true` if trigger is exits from `triggerEvents` state otherwise return `false`.
* Added boolean props `isUnknownTrigger` default is false at `sw-flow-sequence`.
* Added boolean props `isUnknownTrigger` default is false at `sw-flow-detail-general`.
* Added `private readonly FlowEventPersister $flowEventPersister` to `\Shopware\Core\Framework\App\AppStateService::__construct()`
