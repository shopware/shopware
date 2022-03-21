---
title: Fix delete of orders without customer_id
issue: NEXT-20340


---
# Core
* Changed `\Shopware\Core\Checkout\Customer\Subscriber\CustomerMetaFieldSubscriber::updateCustomer` to filter out customer without an id.
