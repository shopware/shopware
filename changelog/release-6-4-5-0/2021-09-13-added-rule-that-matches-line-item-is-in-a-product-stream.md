---
title: Added rule that matches line item is in a product stream
issue: NEXT-8057
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `LineItemInProductStreamRule` for matching a line item is in a dynamic product group
* Added `ManyToManyIdField` property `streamIds` in `ProductDefinition`
___
# Administration
* Added `sw-condition-line-item-in-product-stream` for setting up rule builder line item in dynamic product group conditions
