---
title: Create the new tab "Details" in the order module
issue: NEXT-16682
flag: FEATURE_NEXT_7530
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com 
---
# Administration
* Added `fillDigits` prop to `sw-number-field` to always show zero digits in a number field.
* Changed `sw-number-field` and`sw-order-promotion-tag-field` to correctly overwrite template while extending.
* Changed `sw-order-promotion-tag-field` and `sw-tagged-field` to correctly not allow deletions on disabled.
* Added component `sw-order-promotion-field`, which shows a `sw-order-promotion-tag-field` and a switch field for automatic promotions.
* Added component `sw-order-details-state-card`, which allows an easy state change for an order state association.
