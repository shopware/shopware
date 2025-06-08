---
title: Remove method in customer deleted event.
issue: NEXT-17899
---
# Core
* Removed implementation `CustomerAware` in `CustomerDeletedEvent` class from `Shopware\Core\Checkout\Customer\Event`.
* Removed `getCustomerId` function in `CustomerDeletedEvent` class from `Shopware\Core\Checkout\Customer\Event`.
