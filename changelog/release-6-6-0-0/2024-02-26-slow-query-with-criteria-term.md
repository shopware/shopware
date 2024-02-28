---
title: Slow query with criteria term
issue: NEXT-34023
author: oskroblin Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Removed `SearchRanking` from `product.categories` and `product.tags` association to improve search performance when providing a criteria term via API
