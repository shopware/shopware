---
title: Customer is created despite error by using Admin API
issue: NEXT-38225
---
# Core
* Changed method `onCustomerWritten` of `Shopware\Core\Checkout\Customer\Subscriber\CustomerFlowEventsSubscriber` to delete customer if an error occurs during the customer creation process.
