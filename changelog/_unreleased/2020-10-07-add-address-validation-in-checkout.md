---
title: Add address validation in Checkout
issue: NEXT-9581
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @m.stegmeyer
---
# Core
* Changed `AddressValidator` to check for disabled countries and validation issues of both billing and shipping addresses.
* Added loading of countries of the Sales Channel to `SalesChannelContext`
