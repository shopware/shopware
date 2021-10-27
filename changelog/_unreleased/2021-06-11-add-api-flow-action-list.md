---
title: Add api flow action list
issue: NEXT-15558
---
# Core
* Added new API `GET: /api/_info/actions.json` at `Shopware\Core\Framework\Api\Controller\InfoController` which used to return lists flow action.
* Added `FlowActionCollector`, classes at `Shopware\Core\Content\Flow\Action`.
* Added `FlowActionCollectorResponse` class at `Shopware\Core\Content\Flow\Action`.
* Added `FlowActionDefinition` class at `Shopware\Core\Content\Flow\Action`
* Added `AddTagAction`, `RemoveTagAction`, and `SetOrderStateAction` class at `Shopware\Core\Content\Flow\Action`.
* Removed `AddOrderTagAction` class at `Shopware\Core\Content\Flow\Action`.
* Added `FlowActionCollectorEvent` to dispatch when have a collect flow action.
* Added `UserAware` interfaces at `Shopware\Core\Framework\Event`.
* Added `orderAware`, `customerAware`, `webhookAware`, `userAware` properties and getter, setter for them into class `BusinessEventDefinition` at `Shopware\Core\Framework\Event`.
* Added `getCustomerId` function into `CustomerAccountRecoverRequestEvent`, `CustomerChangedPaymentMethodEvent`, `CustomerDeletedEvent`, `CustomerDoubleOptInRegistrationEvent`, `CustomerGroupRegistrationAccepted`, `CustomerGroupRegistrationDeclined`, `CustomerLoginEvent`, `CustomerLogoutEvent`, `CustomerRegisterEvent` and `DoubleOptInGuestOrderEvent` at `Shopware\Core\Checkout\Customer\Event`.
* Added `getOrderId` function into `OrderStateMachineStateChangeEvent` at `Shopware\Core\Checkout\Order\Event`.
* Added `getUserId` function into `UserRecoveryRequestEvent` at `Shopware\Core\System\User\Recovery`.
