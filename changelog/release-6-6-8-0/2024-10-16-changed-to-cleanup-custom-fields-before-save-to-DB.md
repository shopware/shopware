---
title: Changed to cleanup custom fields before save to DB
issue: NEXT-38579
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Order\OrderPersister::persist` to clean up `customFields` before to save DB.
