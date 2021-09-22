---
title: Remind admin order payments
issue: NEXT-16444
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com 
author_github: seggewiss
---
# Administration
* Changed `sw-order-create-details-footer` to only allow payment methods with the `afterOrderEnabled` flag.
* Added `remindPayment` action to `swOrder` state used by `sw-order-create`.
* Added payment reminder modal to `sw-order-create`.
