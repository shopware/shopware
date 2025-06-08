---
title: customer variables in E-Mail-Templates do not work
issue: NEXT-31213
---
# Core
* Added `defaultBillingAddress` and `defaultShippingAddress` associations to `Customer` entity in `src/Core/Content/Flow/Dispatching/Storer/CustomerStorer.php` to allow access to the default addresses of a customer.
