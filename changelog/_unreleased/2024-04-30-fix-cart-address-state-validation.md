---
title: Fix cart address state validation
issue: NEXT-36023
author: Alexander Bischko
author_email: alexander@bischko.de
author_github: divide29
---
# Core
* Changed `AddressValidator` to use misplaced `getActiveShippingAddress()` and `getActiveBillingAddress()` correctly
