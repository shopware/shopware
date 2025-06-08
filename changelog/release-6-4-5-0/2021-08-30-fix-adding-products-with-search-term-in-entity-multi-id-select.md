---
title: Fix adding products with search term in entity multi id select
issue: NEXT-16806
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Administration
* Changed `sw-entity-multi-id-select` to unset search term in criteria when IDs change to properly present all previously selected products
