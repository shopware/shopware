---
title: Remove outdated payment blocked errors in recalculation
issue: NEXT-14172
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed `PaymentMethodValidator` to remove instances of `PaymentMethodBlockedError` from cart if they were added in previous calculations and can be allowed in recalculation
