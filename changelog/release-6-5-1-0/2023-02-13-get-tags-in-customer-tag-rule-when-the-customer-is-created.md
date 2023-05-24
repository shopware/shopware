---
title: Get tags in CustomerTagRule when the customer is created
issue: NEXT-23878
---
# Core
* Changed `Shopware\Core\Checkout\Customer\Subscriber\CustomerFlowEventsSubscriber` to run the customer indexer manually before restoring the context.
