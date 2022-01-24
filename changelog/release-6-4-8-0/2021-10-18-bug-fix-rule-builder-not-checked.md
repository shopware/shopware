---
title: Bug fix Rule Builder not checked
issue: NEXT-17890
---
# Core
* Added new class `CustomerFlowEventsSubscriber` in `Shopware\Core\Checkout\Customer\Subscriber` to subscriber the`CustomerEvents::CUSTOMER_WRITTEN_EVENT` and dispatching the `CustomerRegisterEvent` and `CustomerChangedPaymentMethodEvent`
* Added new method `getCustomerId` in `Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters` to add more parameters `customerId`
* Changed the `get` function in `Shopware\Core\System\SalesChannel\Context\SalesChannelContextService` to support get the sales channel context with customer
* Changed the `accept` and the `decline` functions in `Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController` to build the new customer context before dispatching the event
* Changed the `generateUserRecovery` function in `Shopware\Core\System\User\Recovery\UserRecoveryService` to build the new user context before dispatching the event
