---
title: Fix automatically applied promotions does not work after save the order
issue: NEXT-37034
---
# Administration
* Changed `deleteAutomaticPromotions` method in `sw-order-promotion-field` component to replaced `syncDeleted` to `delete`.
