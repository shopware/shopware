---
title: Added option to match all for line item rules
issue: NEXT-14317
flag: FEATURE_NEXT_17016
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `AllLineItemsRule` container to allow matching the entirety of the cart's line items with conditions supporting line item scope.

___
# Administration
* Added `sw-condition-base-line-item` component to extend specific line item condition components from.
* Added `sw-condition-all-line-items-container` component for handling conditions that contains condition components extending `sw-condition-base-line-item`
* Changed line item rule components to extend `sw-condition-base-line-item`.
