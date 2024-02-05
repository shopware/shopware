---
title: Enforce id sorting
issue: NEXT-29439
author: oskroblin Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Added always a `FieldSorting(id)` when generating the DAL sorting for an ProductSortingEntity. If sorting also contains id field, fallback will be skipped (considering: `id` and `product.id` as primary key sorting) 
