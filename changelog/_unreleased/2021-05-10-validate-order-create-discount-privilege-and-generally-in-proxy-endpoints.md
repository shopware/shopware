---
title: validate order create discount privilege and generally in proxy endpoints
issue: NEXT-15171
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `AdminSalesChannelApiSource` to pass original context to `AclWriteValidator`. Fixes an issue where priviliges would not be validated as a new `SalesChannelContext` is created in `SalesChannelProxyController` which would lead to the context source not being an instance of `AdminApiSource`, ultimately circumventing the validation.
* Added `AclOrderCreateDiscountValidator` to check for `order:create:discount` on pre-write of credit order line items
