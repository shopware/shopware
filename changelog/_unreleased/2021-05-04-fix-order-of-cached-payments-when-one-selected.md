---
title: Fix order of cached payments when one selected
issue: NEXT-15044
author: David Neustadt
author_email: d.neustadt@shopware.com 
---
# API
* Added `SortedPaymentMethodRoute` decorator of `PaymentMethodRoute` which takes priority over `CachedPaymentMethodRoute` and sorts possibly cached payment method results
