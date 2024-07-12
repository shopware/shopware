---
title: Fix the inability to use admin order when the system default language is not available in the sales channel
issue: NEXT-36433
---
# Administration
* Changed `onCheckCustomer` method in `sw-order-customer-grid` component to set the default language ID to the customer language ID if the language is unavailable in the sales channel.
